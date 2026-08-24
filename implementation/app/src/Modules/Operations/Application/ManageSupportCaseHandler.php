<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class ManageSupportCaseHandler
{
    private const array TRANSITIONS = [
        'Open' => ['Acknowledged', 'InProgress'],
        'Acknowledged' => ['InProgress', 'WaitingRequester', 'Resolved'],
        'InProgress' => ['WaitingRequester', 'Resolved'],
        'WaitingRequester' => ['InProgress', 'Resolved'],
        'Resolved' => ['InProgress', 'Closed'],
        'Closed' => [],
    ];

    public function __construct(private readonly PostgresOperatorAuditRepository $audit) {}

    /** @return array<string, mixed> */
    public function preview(
        string $reference,
        string $operatorUserId,
        int $expectedRevision,
        string $status,
        string $assignment,
        string $reasonCode,
    ): array {
        $case = $this->caseByReference($reference, false);
        $proposal = $this->proposal($case, $operatorUserId, $expectedRevision, $status, $assignment, $reasonCode);

        return $this->previewResponse($case, $proposal, $reasonCode);
    }

    /** @return array<string, mixed> */
    public function apply(
        string $reference,
        string $operatorUserId,
        string $operatorSessionId,
        int $expectedRevision,
        string $status,
        string $assignment,
        string $reasonCode,
        string $previewFingerprint,
        string $idempotencyKey,
        ?string $correlationId,
    ): array {
        $scope = 'support.manage:'.strtoupper($reference);
        $requestFingerprint = $this->requestFingerprint(
            $reference,
            $operatorUserId,
            $expectedRevision,
            $status,
            $assignment,
            $reasonCode,
            $previewFingerprint,
        );

        return DB::transaction(function () use (
            $reference,
            $operatorUserId,
            $operatorSessionId,
            $expectedRevision,
            $status,
            $assignment,
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
                    throw new SupportCaseActionException('OperatorIdempotencyConflict', 'Cette clé d’idempotence a déjà été utilisée avec une autre action.', 409);
                }
                $response = is_array($replay->response)
                    ? $replay->response
                    : json_decode((string) $replay->response, true, 512, JSON_THROW_ON_ERROR);

                return [...$response, 'replayed' => true];
            }

            $case = $this->caseByReference($reference, true);
            $proposal = $this->proposal($case, $operatorUserId, $expectedRevision, $status, $assignment, $reasonCode);
            $preview = $this->previewResponse($case, $proposal, $reasonCode);
            if (! hash_equals((string) $preview['preview_fingerprint'], $previewFingerprint)) {
                throw new SupportCaseActionException('SupportCasePreviewStale', 'La prévisualisation ne correspond plus à cette action.', 409);
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $nextRevision = (int) $case->revision + 1;
            $updates = [
                'status' => $proposal['status'],
                'assigned_operator_user_id' => $proposal['assigned_operator_user_id'],
                'revision' => $nextRevision,
                'updated_at' => $now->format('Y-m-d H:i:sP'),
            ];
            if ($proposal['status'] === 'Resolved' && (string) $case->status !== 'Resolved') {
                $updates['resolved_at'] = $now->format('Y-m-d H:i:sP');
            } elseif ($proposal['status'] === 'InProgress' && (string) $case->status === 'Resolved') {
                $updates['resolved_at'] = null;
                $updates['closed_at'] = null;
            }
            if ($proposal['status'] === 'Closed') {
                $updates['closed_at'] = $now->format('Y-m-d H:i:sP');
            }

            $updated = DB::table('operations.support_cases')
                ->where('id', $case->id)
                ->where('revision', $case->revision)
                ->update($updates);
            if ($updated !== 1) {
                throw new SupportCaseActionException('SupportCaseRevisionConflict', 'Le dossier a été modifié par un autre opérateur.', 409);
            }

            if ((string) $case->status !== $proposal['status']) {
                $this->event((string) $case->id, 'StatusChanged', $proposal['status'], $operatorUserId, $reasonCode, $now);
            }
            if (($case->assigned_operator_user_id !== null ? (string) $case->assigned_operator_user_id : null) !== $proposal['assigned_operator_user_id']) {
                $this->event((string) $case->id, 'AssignmentChanged', $proposal['status'], $operatorUserId, $reasonCode, $now);
            }

            $response = [
                'reference' => (string) $case->reference,
                'status' => $proposal['status'],
                'assignment' => $this->assignmentLabel($proposal['assigned_operator_user_id'], $operatorUserId),
                'revision' => $nextRevision,
                'updated_at' => $now->format(DATE_ATOM),
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
                action: 'operator.support-case.managed',
                result: 'Succeeded',
                operatorUserId: $operatorUserId,
                operatorSessionId: $operatorSessionId,
                permission: OperatorPermissionCatalog::SUPPORT_MANAGE,
                targetType: 'SupportCase',
                targetIdHash: hash('sha256', (string) $case->reference),
                correlationId: $correlationId,
                reason: $reasonCode,
                metadata: [
                    'previous_status' => (string) $case->status,
                    'next_status' => $proposal['status'],
                    'assignment' => $response['assignment'],
                    'revision' => $nextRevision,
                ],
                occurredAt: $now,
            );

            return $response;
        });
    }

    private function caseByReference(string $reference, bool $lock): object
    {
        $query = DB::table('operations.support_cases')->where('reference', strtoupper($reference));
        $case = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($case === null) {
            throw new SupportCaseActionException('SupportCaseNotFound', 'Dossier Support introuvable.', 404);
        }

        return $case;
    }

    /** @return array{status: string, assigned_operator_user_id: string|null} */
    private function proposal(object $case, string $operatorUserId, int $expectedRevision, string $status, string $assignment, string $reasonCode): array
    {
        if ((int) $case->revision !== $expectedRevision) {
            throw new SupportCaseActionException('SupportCaseRevisionConflict', 'Le dossier a été modifié depuis son affichage.', 409);
        }
        if (preg_match('/^[a-z0-9._-]{3,64}$/', $reasonCode) !== 1) {
            throw new SupportCaseActionException('SupportCaseReasonInvalid', 'Un motif structuré sans donnée personnelle est requis.', 422);
        }
        $currentStatus = (string) $case->status;
        if ($currentStatus === 'Closed') {
            throw new SupportCaseActionException('SupportCaseClosed', 'Un dossier clos ne peut plus être modifié.', 409);
        }
        $nextStatus = $status === 'Keep' ? $currentStatus : $status;
        if ($nextStatus !== $currentStatus && ! in_array($nextStatus, self::TRANSITIONS[$currentStatus] ?? [], true)) {
            throw new SupportCaseActionException('SupportCaseTransitionInvalid', 'Cette transition de statut n’est pas autorisée.', 409);
        }
        $currentAssignment = $case->assigned_operator_user_id !== null ? (string) $case->assigned_operator_user_id : null;
        $nextAssignment = match ($assignment) {
            'Keep' => $currentAssignment,
            'Self' => $operatorUserId,
            'Unassigned' => null,
            default => throw new SupportCaseActionException('SupportCaseAssignmentInvalid', 'Cette assignation n’est pas autorisée.', 422),
        };
        if ($nextStatus === $currentStatus && $nextAssignment === $currentAssignment) {
            throw new SupportCaseActionException('SupportCaseNoChange', 'La proposition ne modifie pas le dossier.', 422);
        }

        return ['status' => $nextStatus, 'assigned_operator_user_id' => $nextAssignment];
    }

    /** @param array{status: string, assigned_operator_user_id: string|null} $proposal @return array<string, mixed> */
    private function previewResponse(object $case, array $proposal, string $reasonCode): array
    {
        $fingerprint = hash('sha256', implode('|', [
            (string) $case->reference,
            (string) $case->revision,
            (string) $case->status,
            (string) ($case->assigned_operator_user_id ?? ''),
            $proposal['status'],
            (string) ($proposal['assigned_operator_user_id'] ?? ''),
            $reasonCode,
        ]));

        return [
            'reference' => (string) $case->reference,
            'current' => [
                'status' => (string) $case->status,
                'assignment' => $case->assigned_operator_user_id === null ? 'Unassigned' : 'Assigned',
                'revision' => (int) $case->revision,
            ],
            'proposed' => [
                'status' => $proposal['status'],
                'assignment' => $proposal['assigned_operator_user_id'] === null ? 'Unassigned' : 'Assigned',
                'revision' => (int) $case->revision + 1,
            ],
            'reason_code' => $reasonCode,
            'preview_fingerprint' => $fingerprint,
            'effects' => ['Ajout à l’historique append-only', 'Aucun email envoyé', 'Aucune donnée Workspace modifiée'],
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
            || ! in_array(OperatorPermissionCatalog::SUPPORT_MANAGE, $permissions, true)) {
            throw new SupportCaseActionException('OperatorAuthorityChanged', 'L’autorité de la session a changé avant confirmation.', 409);
        }
    }

    private function event(string $caseId, string $type, string $status, string $operatorUserId, string $detailCode, \DateTimeImmutable $now): void
    {
        DB::table('operations.support_case_events')->insert([
            'id' => UuidGenerator::generate(),
            'support_case_id' => $caseId,
            'event_type' => $type,
            'status' => $status,
            'actor_operator_user_id' => $operatorUserId,
            'detail_code' => $detailCode,
            'occurred_at' => $now->format('Y-m-d H:i:sP'),
            'created_at' => $now->format('Y-m-d H:i:sP'),
        ]);
    }

    private function assignmentLabel(?string $assignedOperatorUserId, string $operatorUserId): string
    {
        if ($assignedOperatorUserId === null) {
            return 'Unassigned';
        }

        return hash_equals($assignedOperatorUserId, $operatorUserId) ? 'Self' : 'Assigned';
    }

    private function requestFingerprint(string $reference, string $operatorUserId, int $revision, string $status, string $assignment, string $reason, string $preview): string
    {
        return hash('sha256', implode('|', [strtoupper($reference), $operatorUserId, (string) $revision, $status, $assignment, $reason, $preview]));
    }
}
