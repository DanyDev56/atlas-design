<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Platform\Messaging\Infrastructure\OutboxDeadLetterManager;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class RetryManagedOutboxMessageHandler
{
    public function __construct(
        private readonly OutboxDeadLetterManager $deadLetters,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    /** @return array<string, mixed> */
    public function preview(string $eventId, string $reasonCode): array
    {
        $message = $this->message($eventId, false);
        $this->assertRetryable($message, $reasonCode);

        return $this->previewResponse($message, $reasonCode);
    }

    /** @return array<string, mixed> */
    public function apply(
        string $eventId,
        string $operatorUserId,
        string $operatorSessionId,
        string $reasonCode,
        string $previewFingerprint,
        string $idempotencyKey,
        ?string $correlationId,
    ): array {
        $normalizedEventId = strtolower($eventId);
        $scope = 'outbox.retry:'.$normalizedEventId;
        $requestFingerprint = hash('sha256', implode('|', [
            $normalizedEventId,
            $operatorUserId,
            $reasonCode,
            $previewFingerprint,
        ]));

        return DB::transaction(function () use (
            $normalizedEventId,
            $operatorUserId,
            $operatorSessionId,
            $reasonCode,
            $previewFingerprint,
            $idempotencyKey,
            $correlationId,
            $scope,
            $requestFingerprint,
        ): array {
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$scope.'|'.$idempotencyKey]);
            $this->assertCurrentAuthority($operatorUserId, $operatorSessionId);

            $replay = DB::table('operations.operator_action_idempotency')
                ->where('action_scope', $scope)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($replay !== null) {
                if (! hash_equals((string) $replay->request_fingerprint, $requestFingerprint)) {
                    throw new OutboxRetryActionException('OperatorIdempotencyConflict', 'Cette clé d’idempotence a déjà été utilisée avec une autre action.', 409);
                }
                $response = is_array($replay->response)
                    ? $replay->response
                    : json_decode((string) $replay->response, true, 512, JSON_THROW_ON_ERROR);

                return [...$response, 'replayed' => true];
            }

            $message = $this->message($normalizedEventId, true);
            $this->assertRetryable($message, $reasonCode);
            $preview = $this->previewResponse($message, $reasonCode);
            if (! hash_equals((string) $preview['preview_fingerprint'], $previewFingerprint)) {
                throw new OutboxRetryActionException('OutboxRetryPreviewStale', 'La dead-letter a changé depuis sa prévisualisation.', 409);
            }

            if (! $this->deadLetters->retry($normalizedEventId)) {
                throw new OutboxRetryActionException('OutboxRetryConflict', 'La dead-letter n’est plus disponible pour une reprise.', 409);
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $response = [
                'event_id' => $normalizedEventId,
                'event_type' => (string) $message->event_type,
                'status' => 'Pending',
                'attempts' => 0,
                'scheduled_at' => $now->format(DATE_ATOM),
                'replayed' => false,
            ];
            DB::table('operations.operator_action_idempotency')->insert([
                'id' => UuidGenerator::generate(),
                'action_scope' => $scope,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $requestFingerprint,
                'response' => json_encode($response, JSON_THROW_ON_ERROR),
                'created_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            $this->audit->record(
                action: 'operator.outbox.dead-letter-retried',
                result: 'Succeeded',
                operatorUserId: $operatorUserId,
                operatorSessionId: $operatorSessionId,
                permission: OperatorPermissionCatalog::OUTBOX_RETRY,
                targetType: 'OutboxMessage',
                targetIdHash: hash('sha256', $normalizedEventId),
                correlationId: $correlationId,
                reason: $reasonCode,
                metadata: [
                    'event_type' => (string) $message->event_type,
                    'previous_attempts' => (int) $message->attempts,
                    'next_status' => 'Pending',
                ],
                occurredAt: $now,
            );

            return $response;
        });
    }

    private function message(string $eventId, bool $lock): object
    {
        $query = DB::table('platform.outbox_messages')->where('event_id', strtolower($eventId));
        $message = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($message === null) {
            throw new OutboxRetryActionException('OutboxMessageNotFound', 'Message Outbox introuvable.', 404);
        }

        return $message;
    }

    private function assertRetryable(object $message, string $reasonCode): void
    {
        if ($message->failed_at === null || $message->dispatched_at !== null) {
            throw new OutboxRetryActionException('OutboxMessageNotDeadLetter', 'Seul un message actuellement en dead-letter peut être repris.', 409);
        }
        if (preg_match('/^[a-z0-9._-]{3,64}$/', $reasonCode) !== 1) {
            throw new OutboxRetryActionException('OutboxRetryReasonInvalid', 'Un motif structuré sans donnée personnelle est requis.', 422);
        }
    }

    /** @return array<string, mixed> */
    private function previewResponse(object $message, string $reasonCode): array
    {
        return [
            'event_id' => (string) $message->event_id,
            'event_type' => (string) $message->event_type,
            'current' => [
                'status' => 'DeadLetter',
                'attempts' => (int) $message->attempts,
                'failed_at' => (new \DateTimeImmutable((string) $message->failed_at))->format(DATE_ATOM),
            ],
            'proposed' => [
                'status' => 'Pending',
                'attempts' => 0,
                'availability' => 'Immediate',
            ],
            'reason_code' => $reasonCode,
            'preview_fingerprint' => hash('sha256', implode('|', [
                (string) $message->id,
                (string) $message->event_id,
                (string) $message->event_type,
                hash('sha256', (string) $message->payload),
                (string) $message->attempts,
                (string) $message->failed_at,
                (string) $message->last_error,
                (string) $message->dispatched_at,
                $reasonCode,
            ])),
            'effects' => [
                'Remise en file immédiate ; aucun traitement dans cette requête',
                'Compteur de tentatives et erreur technique réinitialisés',
                'Consommateurs déjà confirmés ignorés grâce aux reçus Inbox',
            ],
        ];
    }

    private function assertCurrentAuthority(string $operatorUserId, string $operatorSessionId): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $row = DB::table('operations.operator_sessions as session')
            ->join('operations.operator_grants as grant', 'grant.id', '=', 'session.grant_id')
            ->where('session.id', $operatorSessionId)
            ->where('session.user_id', $operatorUserId)
            ->lockForUpdate()
            ->select([
                'session.status as session_status',
                'session.expires_at as session_expires_at',
                'session.step_up_at',
                'grant.status as grant_status',
                'grant.expires_at as grant_expires_at',
                'grant.permissions',
            ])
            ->first();
        $permissions = $row !== null
            ? (is_array($row->permissions) ? $row->permissions : json_decode((string) $row->permissions, true, 512, JSON_THROW_ON_ERROR))
            : [];
        $stepUpMinutes = max(1, min(30, (int) config('operations.backoffice.step_up_minutes', 10)));
        $stepUpValid = $row !== null
            && $row->step_up_at !== null
            && (new \DateTimeImmutable((string) $row->step_up_at))->modify('+'.$stepUpMinutes.' minutes') > $now;
        if ($row === null
            || (string) $row->session_status !== 'Active'
            || new \DateTimeImmutable((string) $row->session_expires_at) <= $now
            || (string) $row->grant_status !== 'Active'
            || ($row->grant_expires_at !== null && new \DateTimeImmutable((string) $row->grant_expires_at) <= $now)
            || ! in_array(OperatorPermissionCatalog::OUTBOX_RETRY, $permissions, true)
            || ! $stepUpValid) {
            throw new OutboxRetryActionException('OperatorAuthorityChanged', 'L’autorité ou le step-up de la session a changé avant confirmation.', 409);
        }
    }
}
