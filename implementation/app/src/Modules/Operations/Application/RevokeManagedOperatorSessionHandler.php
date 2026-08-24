<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class RevokeManagedOperatorSessionHandler
{
    public function __construct(private readonly PostgresOperatorAuditRepository $audit) {}

    /** @return array<string, mixed> */
    public function preview(string $reference, string $actorSessionId, int $expectedRevision, string $reasonCode): array
    {
        $target = $this->targetByReference($reference, false);
        $this->assertProposal($target, $actorSessionId, $expectedRevision, $reasonCode);

        return $this->previewResponse($target, $reasonCode);
    }

    /** @return array<string, mixed> */
    public function apply(
        string $reference,
        string $actorUserId,
        string $actorSessionId,
        int $expectedRevision,
        string $reasonCode,
        string $previewFingerprint,
        string $idempotencyKey,
        ?string $correlationId,
    ): array {
        $scope = 'operator-session.revoke:'.strtoupper($reference);
        $requestFingerprint = hash('sha256', implode('|', [
            strtoupper($reference), $actorUserId, (string) $expectedRevision, $reasonCode, $previewFingerprint,
        ]));

        return DB::transaction(function () use ($reference, $actorUserId, $actorSessionId, $expectedRevision, $reasonCode, $previewFingerprint, $idempotencyKey, $correlationId, $scope, $requestFingerprint): array {
            DB::selectOne("SELECT pg_advisory_xact_lock(hashtextextended('operator-session-revoke-actions', 0))");
            $this->assertCurrentAuthority($actorUserId, $actorSessionId);

            $replay = DB::table('operations.operator_action_idempotency')
                ->where('action_scope', $scope)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($replay !== null) {
                if (! hash_equals((string) $replay->request_fingerprint, $requestFingerprint)) {
                    throw new OperatorSessionActionException('OperatorIdempotencyConflict', 'Cette clé d’idempotence a déjà été utilisée avec une autre action.', 409);
                }
                $response = is_array($replay->response)
                    ? $replay->response
                    : json_decode((string) $replay->response, true, 512, JSON_THROW_ON_ERROR);

                return [...$response, 'replayed' => true];
            }

            $target = $this->targetByReference($reference, true);
            $this->assertProposal($target, $actorSessionId, $expectedRevision, $reasonCode);
            $preview = $this->previewResponse($target, $reasonCode);
            if (! hash_equals((string) $preview['preview_fingerprint'], $previewFingerprint)) {
                throw new OperatorSessionActionException('OperatorSessionPreviewStale', 'La prévisualisation ne correspond plus à cette session.', 409);
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $nextRevision = (int) $target->revision + 1;
            $updated = DB::table('operations.operator_sessions')
                ->where('id', $target->id)
                ->where('status', 'Active')
                ->where('revision', $target->revision)
                ->update([
                    'status' => 'Revoked',
                    'revision' => $nextRevision,
                    'revoked_at' => $now->format('Y-m-d H:i:sP'),
                ]);
            if ($updated !== 1) {
                throw new OperatorSessionActionException('OperatorSessionRevisionConflict', 'La session a été modifiée par une autre opération.', 409);
            }

            $response = [
                'reference' => (string) $target->reference,
                'status' => 'Revoked',
                'revision' => $nextRevision,
                'revoked_at' => $now->format(DATE_ATOM),
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
                action: 'operator.session.target-revoked',
                result: 'Succeeded',
                operatorUserId: $actorUserId,
                operatorSessionId: $actorSessionId,
                permission: OperatorPermissionCatalog::SESSIONS_REVOKE,
                targetType: 'OperatorSession',
                targetIdHash: hash('sha256', (string) $target->reference),
                correlationId: $correlationId,
                reason: $reasonCode,
                metadata: ['revision' => $nextRevision],
                occurredAt: $now,
            );

            return $response;
        });
    }

    private function targetByReference(string $reference, bool $lock): object
    {
        $query = DB::table('operations.operator_sessions')->where('reference', strtoupper($reference));
        $target = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($target === null) {
            throw new OperatorSessionActionException('OperatorSessionNotFound', 'Session opérateur introuvable.', 404);
        }

        return $target;
    }

    private function assertProposal(object $target, string $actorSessionId, int $expectedRevision, string $reasonCode): void
    {
        if (hash_equals((string) $target->id, $actorSessionId)) {
            throw new OperatorSessionActionException('OperatorSelfSessionRevocationForbidden', 'Utilisez Déconnexion pour fermer votre session courante.', 409);
        }
        if ((int) $target->revision !== $expectedRevision) {
            throw new OperatorSessionActionException('OperatorSessionRevisionConflict', 'La session a été modifiée depuis son affichage.', 409);
        }
        if ((string) $target->status !== 'Active' || new \DateTimeImmutable((string) $target->expires_at) <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            throw new OperatorSessionActionException('OperatorSessionNotActive', 'Cette session n’est plus active.', 409);
        }
        if (preg_match('/^[a-z0-9._-]{3,64}$/', $reasonCode) !== 1) {
            throw new OperatorSessionActionException('OperatorSessionReasonInvalid', 'Un motif structuré sans donnée personnelle est requis.', 422);
        }
    }

    /** @return array<string, mixed> */
    private function previewResponse(object $target, string $reasonCode): array
    {
        return [
            'reference' => (string) $target->reference,
            'current' => ['status' => 'Active', 'revision' => (int) $target->revision],
            'proposed' => ['status' => 'Revoked', 'revision' => (int) $target->revision + 1],
            'reason_code' => $reasonCode,
            'preview_fingerprint' => hash('sha256', implode('|', [
                (string) $target->reference, (string) $target->id, (string) $target->user_id,
                (string) $target->revision, (string) $target->status, (string) $target->expires_at, $reasonCode,
            ])),
            'effects' => [
                'Jeton Operator ciblé immédiatement invalidé',
                'Sessions Workspace inchangées',
                'Grant et facteur MFA inchangés',
            ],
        ];
    }

    private function assertCurrentAuthority(string $actorUserId, string $actorSessionId): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $row = DB::table('operations.operator_sessions as session')
            ->join('operations.operator_grants as grant', 'grant.id', '=', 'session.grant_id')
            ->where('session.id', $actorSessionId)
            ->where('session.user_id', $actorUserId)
            ->lockForUpdate()
            ->select(['session.status as session_status', 'session.expires_at as session_expires_at', 'grant.status as grant_status', 'grant.expires_at as grant_expires_at', 'grant.permissions'])
            ->first();
        $permissions = $row !== null
            ? (is_array($row->permissions) ? $row->permissions : json_decode((string) $row->permissions, true, 512, JSON_THROW_ON_ERROR))
            : [];
        if ($row === null
            || (string) $row->session_status !== 'Active'
            || new \DateTimeImmutable((string) $row->session_expires_at) <= $now
            || (string) $row->grant_status !== 'Active'
            || ($row->grant_expires_at !== null && new \DateTimeImmutable((string) $row->grant_expires_at) <= $now)
            || ! in_array(OperatorPermissionCatalog::SESSIONS_REVOKE, $permissions, true)) {
            throw new OperatorSessionActionException('OperatorAuthorityChanged', 'L’autorité de la session a changé avant confirmation.', 409);
        }
    }
}
