<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

final class ManageApprovedDataExportHandler
{
    public function __construct(
        private readonly OutboxWriter $outbox,
        private readonly Encrypter $encrypter,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    /** @return array<string, mixed> */
    public function previewRequest(string $dataRequestReference, string $operatorUserId, string $reasonCode): array
    {
        $request = $this->dataRequest($dataRequestReference, false);
        $this->assertRequestable($request, $reasonCode);

        return [
            'data_request_reference' => (string) $request->reference,
            'scope' => 'WorkspaceDataV1',
            'current_status' => (string) $request->status,
            'proposed_status' => 'AwaitingApproval',
            'requires_distinct_approver' => true,
            'preview_fingerprint' => $this->requestPreviewFingerprint($request, $operatorUserId, $reasonCode),
            'effects' => [
                'Crée une demande d’export sans lire les données du Workspace',
                'Bloque l’approbation par ce même opérateur',
                'Ne génère et ne transmet encore aucun fichier',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function request(
        string $dataRequestReference,
        string $operatorUserId,
        string $operatorSessionId,
        string $reasonCode,
        string $previewFingerprint,
        string $idempotencyKey,
        ?string $correlationId,
    ): array {
        $scope = 'data-export.request:'.strtoupper($dataRequestReference);
        $requestFingerprint = hash('sha256', implode('|', [$scope, $operatorUserId, $reasonCode, $previewFingerprint]));

        return DB::transaction(function () use ($dataRequestReference, $operatorUserId, $operatorSessionId, $reasonCode, $previewFingerprint, $idempotencyKey, $correlationId, $scope, $requestFingerprint): array {
            $replay = $this->beginIdempotentAction($scope, $idempotencyKey, $requestFingerprint);
            if ($replay !== null) {
                return [...$replay, 'replayed' => true];
            }
            $this->assertCurrentAuthority($operatorUserId, $operatorSessionId, OperatorPermissionCatalog::EXPORTS_REQUEST);
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['data-export-request|'.strtoupper($dataRequestReference)]);
            $request = $this->dataRequest($dataRequestReference, true);
            $this->assertRequestable($request, $reasonCode);
            if (! hash_equals($this->requestPreviewFingerprint($request, $operatorUserId, $reasonCode), $previewFingerprint)) {
                throw new DataExportActionException('DataExportPreviewStale', 'La prévisualisation ne correspond plus à cette demande.', 409);
            }

            $now = $this->now();
            $exportId = UuidGenerator::generate();
            $reference = $this->reference();
            DB::table('operations.data_exports')->insert([
                'id' => $exportId, 'reference' => $reference, 'data_request_id' => (string) $request->id,
                'workspace_id' => (string) $request->workspace_id, 'requester_user_id' => (string) $request->requester_user_id,
                'scope' => 'WorkspaceDataV1', 'status' => 'AwaitingApproval',
                'requested_by_operator_user_id' => $operatorUserId, 'approved_by_operator_user_id' => null,
                'reason_code' => $reasonCode, 'revision' => 1, 'requested_at' => $now->format('Y-m-d H:i:sP'),
                'approved_at' => null, 'ready_at' => null, 'downloaded_at' => null, 'expires_at' => null,
                'failure_code' => null, 'created_at' => $now->format('Y-m-d H:i:sP'), 'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            DB::table('operations.data_requests')->where('id', $request->id)->update([
                'status' => 'AwaitingApproval', 'decision_code' => 'export.requested', 'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            $this->event($exportId, 'Requested', 'AwaitingApproval', $operatorUserId, $reasonCode, $now);
            $response = [
                'reference' => $reference, 'data_request_reference' => (string) $request->reference,
                'status' => 'AwaitingApproval', 'revision' => 1, 'requested_at' => $now->format(DATE_ATOM), 'replayed' => false,
            ];
            $this->storeIdempotency($scope, $idempotencyKey, $requestFingerprint, $response, $now);
            $this->audit->record(
                action: 'operator.data-export.requested', result: 'Succeeded', operatorUserId: $operatorUserId,
                operatorSessionId: $operatorSessionId, permission: OperatorPermissionCatalog::EXPORTS_REQUEST,
                targetType: 'DataExport', targetIdHash: hash('sha256', $reference), correlationId: $correlationId,
                reason: $reasonCode, metadata: ['data_request_reference_hash' => hash('sha256', (string) $request->reference), 'scope' => 'WorkspaceDataV1'], occurredAt: $now,
            );

            return $response;
        });
    }

    /** @return array<string, mixed> */
    public function previewApproval(string $exportReference, string $operatorUserId, string $reasonCode): array
    {
        $export = $this->export($exportReference, false);
        $this->assertApprovable($export, $operatorUserId, $reasonCode);

        return [
            'reference' => (string) $export->reference,
            'data_request_reference' => (string) $export->data_request_reference,
            'scope' => (string) $export->scope,
            'current_status' => (string) $export->status,
            'proposed_status' => 'Generating',
            'revision' => (int) $export->revision,
            'preview_fingerprint' => $this->approvalPreviewFingerprint($export, $operatorUserId, $reasonCode),
            'effects' => [
                'Valide la demande d’un autre opérateur',
                'Place un job de génération dans l’Outbox',
                'Produit un JSON chiffré, borné et expirant sans l’envoyer automatiquement',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function approve(
        string $exportReference,
        string $operatorUserId,
        string $operatorSessionId,
        string $reasonCode,
        string $previewFingerprint,
        string $idempotencyKey,
        ?string $correlationId,
    ): array {
        $scope = 'data-export.approve:'.strtoupper($exportReference);
        $requestFingerprint = hash('sha256', implode('|', [$scope, $operatorUserId, $reasonCode, $previewFingerprint]));

        return DB::transaction(function () use ($exportReference, $operatorUserId, $operatorSessionId, $reasonCode, $previewFingerprint, $idempotencyKey, $correlationId, $scope, $requestFingerprint): array {
            $replay = $this->beginIdempotentAction($scope, $idempotencyKey, $requestFingerprint);
            if ($replay !== null) {
                return [...$replay, 'replayed' => true];
            }
            $this->assertCurrentAuthority($operatorUserId, $operatorSessionId, OperatorPermissionCatalog::EXPORTS_APPROVE);
            $export = $this->export($exportReference, true);
            $this->assertApprovable($export, $operatorUserId, $reasonCode);
            if (! hash_equals($this->approvalPreviewFingerprint($export, $operatorUserId, $reasonCode), $previewFingerprint)) {
                throw new DataExportActionException('DataExportPreviewStale', 'La prévisualisation d’approbation n’est plus valable.', 409);
            }

            $now = $this->now();
            $nextRevision = (int) $export->revision + 1;
            DB::table('operations.data_exports')->where('id', $export->id)->where('revision', $export->revision)->update([
                'status' => 'Generating', 'approved_by_operator_user_id' => $operatorUserId,
                'approved_at' => $now->format('Y-m-d H:i:sP'), 'revision' => $nextRevision, 'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            DB::table('operations.data_requests')->where('id', $export->data_request_id)->update([
                'status' => 'InPreparation', 'decision_code' => 'export.approved', 'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            $this->event((string) $export->id, 'Approved', 'Generating', $operatorUserId, $reasonCode, $now);
            $this->outbox->append(new OutgoingMessage(
                eventId: EventId::generate(), eventType: 'operations.data_export.generation_requested',
                payload: ['data_export_id' => (string) $export->id], occurredAt: $now, correlationId: $correlationId,
            ));
            $response = [
                'reference' => (string) $export->reference, 'status' => 'Generating', 'revision' => $nextRevision,
                'approved_at' => $now->format(DATE_ATOM), 'replayed' => false,
            ];
            $this->storeIdempotency($scope, $idempotencyKey, $requestFingerprint, $response, $now);
            $this->audit->record(
                action: 'operator.data-export.approved', result: 'Succeeded', operatorUserId: $operatorUserId,
                operatorSessionId: $operatorSessionId, permission: OperatorPermissionCatalog::EXPORTS_APPROVE,
                targetType: 'DataExport', targetIdHash: hash('sha256', (string) $export->reference), correlationId: $correlationId,
                reason: $reasonCode, metadata: ['scope' => (string) $export->scope, 'revision' => $nextRevision], occurredAt: $now,
            );

            return $response;
        });
    }

    /** @return array{content: string, filename: string, media_type: string, content_fingerprint: string, replayed: bool} */
    public function download(
        string $exportReference,
        string $operatorUserId,
        string $operatorSessionId,
        string $reasonCode,
        string $idempotencyKey,
        ?string $correlationId,
    ): array {
        $this->assertReason($reasonCode);
        $export = $this->exportWithArtifact($exportReference);
        $now = $this->now();
        if (! in_array((string) $export->status, ['Ready', 'Delivered'], true) || $export->ciphertext === null) {
            throw new DataExportActionException('DataExportNotReady', 'Cet export n’est pas prêt au téléchargement.', 409);
        }
        if ($export->expires_at === null || new \DateTimeImmutable((string) $export->expires_at) <= $now) {
            $this->expire((string) $export->id, (string) $export->reference, $operatorUserId, $operatorSessionId, $reasonCode, $correlationId, $now);
            throw new DataExportActionException('DataExportExpired', 'Cet export a expiré et ne peut plus être téléchargé.', 410);
        }
        try {
            $content = $this->encrypter->decryptString((string) $export->ciphertext);
        } catch (\Throwable) {
            throw new DataExportActionException('DataExportArtifactInvalid', 'L’intégrité de l’artefact ne peut pas être vérifiée.', 409);
        }
        if (! hash_equals((string) $export->content_fingerprint, hash('sha256', $content))) {
            throw new DataExportActionException('DataExportArtifactInvalid', 'L’intégrité de l’artefact ne peut pas être vérifiée.', 409);
        }

        $scope = 'data-export.download:'.strtoupper($exportReference);
        $requestFingerprint = hash('sha256', implode('|', [$scope, $operatorUserId, $reasonCode, (string) $export->content_fingerprint]));
        $result = DB::transaction(function () use ($export, $exportReference, $operatorUserId, $operatorSessionId, $reasonCode, $idempotencyKey, $correlationId, $scope, $requestFingerprint): array {
            $replay = $this->beginIdempotentAction($scope, $idempotencyKey, $requestFingerprint);
            if ($replay !== null) {
                return [...$replay, 'replayed' => true];
            }
            $this->assertCurrentAuthority($operatorUserId, $operatorSessionId, OperatorPermissionCatalog::EXPORTS_DOWNLOAD);
            $current = $this->export($exportReference, true);
            $now = $this->now();
            if (! in_array((string) $current->status, ['Ready', 'Delivered'], true)
                || $current->expires_at === null || new \DateTimeImmutable((string) $current->expires_at) <= $now) {
                throw new DataExportActionException('DataExportNotReady', 'Cet export n’est plus disponible au téléchargement.', 409);
            }
            $firstDownload = (string) $current->status === 'Ready';
            if ($firstDownload) {
                DB::table('operations.data_exports')->where('id', $current->id)->update([
                    'status' => 'Delivered', 'downloaded_at' => $now->format('Y-m-d H:i:sP'),
                    'revision' => (int) $current->revision + 1, 'updated_at' => $now->format('Y-m-d H:i:sP'),
                ]);
                DB::table('operations.data_requests')->where('id', $current->data_request_id)->update([
                    'status' => 'Delivered', 'decision_code' => 'export.delivered',
                    'evidence_fingerprint' => (string) $export->content_fingerprint, 'updated_at' => $now->format('Y-m-d H:i:sP'),
                ]);
                $this->event((string) $current->id, 'Downloaded', 'Delivered', $operatorUserId, $reasonCode, $now);
            }
            $response = ['downloaded_at' => $now->format(DATE_ATOM), 'first_download' => $firstDownload, 'replayed' => false];
            $this->storeIdempotency($scope, $idempotencyKey, $requestFingerprint, $response, $now);
            $this->audit->record(
                action: 'operator.data-export.downloaded', result: 'Succeeded', operatorUserId: $operatorUserId,
                operatorSessionId: $operatorSessionId, permission: OperatorPermissionCatalog::EXPORTS_DOWNLOAD,
                targetType: 'DataExport', targetIdHash: hash('sha256', (string) $current->reference), correlationId: $correlationId,
                reason: $reasonCode, metadata: ['first_download' => $firstDownload, 'byte_size' => (int) $export->byte_size], occurredAt: $now,
            );

            return $response;
        });

        return [
            'content' => $content, 'filename' => 'atlas-export-'.strtolower((string) $export->reference).'.json',
            'media_type' => (string) $export->media_type, 'content_fingerprint' => (string) $export->content_fingerprint,
            'replayed' => (bool) $result['replayed'],
        ];
    }

    private function dataRequest(string $reference, bool $lock): object
    {
        $query = DB::table('operations.data_requests')->where('reference', strtoupper($reference));
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($row === null) {
            throw new DataExportActionException('DataRequestNotFound', 'Demande relative aux données introuvable.', 404);
        }

        return $row;
    }

    private function export(string $reference, bool $lock): object
    {
        $query = DB::table('operations.data_exports as export')
            ->join('operations.data_requests as request', 'request.id', '=', 'export.data_request_id')
            ->where('export.reference', strtoupper($reference))
            ->select(['export.*', 'request.reference as data_request_reference', 'request.status as data_request_status']);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($row === null) {
            throw new DataExportActionException('DataExportNotFound', 'Export introuvable.', 404);
        }

        return $row;
    }

    private function exportWithArtifact(string $reference): object
    {
        $row = DB::table('operations.data_exports as export')
            ->leftJoin('operations.data_export_artifacts as artifact', 'artifact.data_export_id', '=', 'export.id')
            ->where('export.reference', strtoupper($reference))
            ->first(['export.*', 'artifact.ciphertext', 'artifact.content_fingerprint', 'artifact.byte_size', 'artifact.media_type']);
        if ($row === null) {
            throw new DataExportActionException('DataExportNotFound', 'Export introuvable.', 404);
        }

        return $row;
    }

    private function assertRequestable(object $request, string $reasonCode): void
    {
        $this->assertReason($reasonCode);
        if (! in_array((string) $request->request_type, ['Access', 'Portability'], true)) {
            throw new DataExportActionException('DataExportRequestTypeInvalid', 'Seules les demandes d’accès ou de portabilité peuvent produire cet export.', 409);
        }
        if ((string) $request->status !== 'Qualified') {
            throw new DataExportActionException('DataExportRequestStateInvalid', 'La demande doit être qualifiée avant de préparer un export.', 409);
        }
        if ($request->identity_verified_at === null || $request->ownership_verified_at === null) {
            throw new DataExportActionException('DataExportVerificationRequired', 'L’identité et la propriété du Workspace doivent être vérifiées.', 409);
        }
        if (DB::table('operations.data_exports')->where('data_request_id', $request->id)->exists()) {
            throw new DataExportActionException('DataExportAlreadyRequested', 'Un export existe déjà pour cette demande.', 409);
        }
    }

    private function assertApprovable(object $export, string $operatorUserId, string $reasonCode): void
    {
        $this->assertReason($reasonCode);
        if ((string) $export->status !== 'AwaitingApproval' || (string) $export->data_request_status !== 'AwaitingApproval') {
            throw new DataExportActionException('DataExportStateInvalid', 'Cet export n’attend plus d’approbation.', 409);
        }
        if (hash_equals((string) $export->requested_by_operator_user_id, $operatorUserId)) {
            throw new DataExportActionException('DataExportDistinctApproverRequired', 'Un autre opérateur doit approuver cet export.', 403);
        }
    }

    private function assertReason(string $reasonCode): void
    {
        if (preg_match('/^[a-z0-9._-]{3,64}$/', $reasonCode) !== 1) {
            throw new DataExportActionException('DataExportReasonInvalid', 'Un motif structuré sans donnée personnelle est requis.', 422);
        }
    }

    private function requestPreviewFingerprint(object $request, string $operatorUserId, string $reasonCode): string
    {
        return hash('sha256', implode('|', [(string) $request->reference, (string) $request->status, (string) $request->updated_at, $operatorUserId, $reasonCode, 'WorkspaceDataV1']));
    }

    private function approvalPreviewFingerprint(object $export, string $operatorUserId, string $reasonCode): string
    {
        return hash('sha256', implode('|', [(string) $export->reference, (string) $export->status, (string) $export->revision, (string) $export->requested_by_operator_user_id, $operatorUserId, $reasonCode]));
    }

    private function assertCurrentAuthority(string $operatorUserId, string $operatorSessionId, string $permission): void
    {
        $now = $this->now();
        $row = DB::table('operations.operator_sessions as session')
            ->join('operations.operator_grants as grant', 'grant.id', '=', 'session.grant_id')
            ->where('session.id', $operatorSessionId)->where('session.user_id', $operatorUserId)->lockForUpdate()
            ->first(['session.status as session_status', 'session.expires_at as session_expires_at', 'grant.status as grant_status', 'grant.expires_at as grant_expires_at', 'grant.permissions']);
        $permissions = $row !== null ? (is_array($row->permissions) ? $row->permissions : json_decode((string) $row->permissions, true, 512, JSON_THROW_ON_ERROR)) : [];
        if ($row === null || (string) $row->session_status !== 'Active' || new \DateTimeImmutable((string) $row->session_expires_at) <= $now
            || (string) $row->grant_status !== 'Active' || ($row->grant_expires_at !== null && new \DateTimeImmutable((string) $row->grant_expires_at) <= $now)
            || ! in_array($permission, $permissions, true)) {
            throw new DataExportActionException('OperatorAuthorityChanged', 'L’autorité de la session a changé avant confirmation.', 409);
        }
    }

    /** @return array<string, mixed>|null */
    private function beginIdempotentAction(string $scope, string $key, string $fingerprint): ?array
    {
        DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$scope.'|'.$key]);
        $row = DB::table('operations.operator_action_idempotency')->where('action_scope', $scope)->where('idempotency_key', $key)->lockForUpdate()->first();
        if ($row === null) {
            return null;
        }
        if (! hash_equals((string) $row->request_fingerprint, $fingerprint)) {
            throw new DataExportActionException('OperatorIdempotencyConflict', 'Cette clé d’idempotence a déjà été utilisée avec une autre action.', 409);
        }

        return is_array($row->response) ? $row->response : json_decode((string) $row->response, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $response */
    private function storeIdempotency(string $scope, string $key, string $fingerprint, array $response, \DateTimeImmutable $now): void
    {
        DB::table('operations.operator_action_idempotency')->insert([
            'id' => UuidGenerator::generate(), 'action_scope' => $scope, 'idempotency_key' => $key,
            'request_fingerprint' => $fingerprint, 'response' => json_encode($response, JSON_THROW_ON_ERROR),
            'created_at' => $now->format('Y-m-d H:i:sP'),
        ]);
    }

    private function event(string $exportId, string $type, string $status, ?string $actorId, string $detailCode, \DateTimeImmutable $now): void
    {
        DB::table('operations.data_export_events')->insert([
            'id' => UuidGenerator::generate(), 'data_export_id' => $exportId, 'event_type' => $type, 'status' => $status,
            'actor_operator_user_id' => $actorId, 'detail_code' => $detailCode,
            'occurred_at' => $now->format('Y-m-d H:i:sP'), 'created_at' => $now->format('Y-m-d H:i:sP'),
        ]);
    }

    private function expire(string $exportId, string $reference, string $operatorUserId, string $operatorSessionId, string $reasonCode, ?string $correlationId, \DateTimeImmutable $now): void
    {
        DB::transaction(function () use ($exportId, $reference, $operatorUserId, $operatorSessionId, $reasonCode, $correlationId, $now): void {
            $this->assertCurrentAuthority($operatorUserId, $operatorSessionId, OperatorPermissionCatalog::EXPORTS_DOWNLOAD);
            $export = DB::table('operations.data_exports')->where('id', $exportId)->lockForUpdate()->first();
            if ($export === null || ! in_array((string) $export->status, ['Ready', 'Delivered'], true)) {
                return;
            }
            DB::table('operations.data_exports')->where('id', $exportId)->update([
                'status' => 'Expired', 'revision' => (int) $export->revision + 1, 'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            DB::table('operations.data_export_artifacts')->where('data_export_id', $exportId)->delete();
            $this->event($exportId, 'Expired', 'Expired', $operatorUserId, 'export.expired', $now);
            $this->audit->record(
                action: 'operator.data-export.download-denied', result: 'Denied', operatorUserId: $operatorUserId,
                operatorSessionId: $operatorSessionId, permission: OperatorPermissionCatalog::EXPORTS_DOWNLOAD,
                targetType: 'DataExport', targetIdHash: hash('sha256', $reference), correlationId: $correlationId,
                reason: $reasonCode, metadata: ['error' => 'DataExportExpired'], occurredAt: $now,
            );
        });
    }

    private function reference(): string
    {
        return 'EXP-'.strtoupper(substr(str_replace('-', '', UuidGenerator::generate()), 0, 12));
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
