<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\ClientHistoryImportRequested;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientHistoryImportPreviewRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientHistoryImportRunRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConfirmHistoricalClientsImportHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientHistoryImportPreviewRepository $previews,
        private readonly PostgresClientHistoryImportRunRepository $importRuns,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $sessionId,
        string $previewId,
        string $packageHash,
        string $sourceSystem,
        string $sourceExportedAt,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorizeElevated(
            $actorUserId,
            $workspaceId,
            'crm.clients.import-history',
            $sessionId,
        );

        $normalizedPackageHash = preg_replace('/^sha256:/i', '', $packageHash) ?? $packageHash;
        $scope = 'crm.confirm_historical_clients_import';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId,
            $previewId,
            $normalizedPackageHash,
            trim($sourceSystem),
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        $existingRun = $this->importRuns->findActiveByPackageHash($workspaceId, $normalizedPackageHash);

        if ($existingRun !== null) {
            $response = $this->serializeRun($existingRun, $requestId, $correlationId);
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        }

        $preview = $this->previews->findById($workspaceId, $previewId);

        if ($preview === null) {
            throw new \DomainException('Preview not found.');
        }

        if ($preview['package_hash'] !== $normalizedPackageHash) {
            throw new \DomainException('Package hash mismatch.');
        }

        if ($preview['source_system'] !== trim($sourceSystem)) {
            throw new \DomainException('Source system mismatch.');
        }

        $previewExportedAt = new \DateTimeImmutable((string) $preview['source_exported_at']);
        $inputExportedAt = new \DateTimeImmutable($sourceExportedAt);

        if ($previewExportedAt->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM) !== $inputExportedAt->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM)) {
            throw new \DomainException('Source export date mismatch.');
        }

        if ($preview['valid_for_confirmation'] !== true) {
            throw new \DomainException('Preview is not valid for confirmation.');
        }

        $importRunId = (string) Str::uuid();
        $clientCount = max(0, (int) ($preview['valid_row_count'] ?? 0));
        $now = now();
        $occurredAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $run = DB::transaction(function () use (
            $importRunId,
            $workspaceId,
            $previewId,
            $sourceSystem,
            $preview,
            $normalizedPackageHash,
            $clientCount,
            $actorUserId,
            $now,
            $occurredAt,
            $correlationId,
            $requestId,
        ): array {
            $this->importRuns->insert([
                'id' => $importRunId,
                'workspace_id' => $workspaceId,
                'preview_id' => $previewId,
                'source_system' => $sourceSystem,
                'source_exported_at' => $preview['source_exported_at'],
                'package_hash' => $normalizedPackageHash,
                'status' => 'Processing',
                'client_count' => $clientCount,
                'processed_count' => 0,
                'created_by' => $actorUserId,
                'created_at' => $now->format('Y-m-d H:i:sP'),
                'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);

            $this->outbox->append(OutgoingMessage::fromDomainEvent(
                new ClientHistoryImportRequested(
                    importRunId: $importRunId,
                    workspaceId: $workspaceId,
                    sourceSystem: $sourceSystem,
                    packageHash: $normalizedPackageHash,
                    clientCount: $clientCount,
                    eventId: EventId::generate(),
                    occurredAt: $occurredAt,
                ),
                correlationId: $correlationId,
            ));

            return $this->serializeRun([
                'id' => $importRunId,
                'workspace_id' => $workspaceId,
                'status' => 'Processing',
                'client_count' => $clientCount,
                'processed_count' => 0,
                'preview_id' => $previewId,
                'source_system' => $sourceSystem,
                'package_hash' => $normalizedPackageHash,
            ], $requestId, $correlationId);
        });

        $this->idempotency->store($scope, $requestId, $fingerprint, $run);

        return $run;
    }

    /** @param array<string, mixed> $run */
    private function serializeRun(array $run, string $requestId, ?string $correlationId): array
    {
        return [
            'import_run_id' => $run['id'] ?? $run['import_run_id'],
            'workspace_id' => $run['workspace_id'] ?? null,
            'preview_id' => $run['preview_id'] ?? null,
            'source_system' => $run['source_system'] ?? null,
            'package_hash' => $run['package_hash'] ?? null,
            'status' => $run['status'] ?? 'Processing',
            'client_count' => (int) ($run['client_count'] ?? 0),
            'processed_count' => (int) ($run['processed_count'] ?? 0),
            'correlation_id' => $correlationId,
            'request_id' => $requestId,
        ];
    }
}
