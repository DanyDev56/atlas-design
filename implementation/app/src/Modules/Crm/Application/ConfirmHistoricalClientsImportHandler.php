<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Application\Jobs\ImportHistoricalClientsJob;
use Atlas\Modules\Crm\Domain\ClientHistoryImportRequested;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientHistoryImportPreviewRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientHistoryImportRunRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConfirmHistoricalClientsImportHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientHistoryImportPreviewRepository $previews,
        private readonly PostgresClientHistoryImportRunRepository $importRuns,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $previewId,
        string $packageHash,
        string $sourceSystem,
        string $sourceExportedAt,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.clients.import-history');

        $preview = $this->previews->findById($workspaceId, $previewId);

        if ($preview === null) {
            throw new \DomainException('Preview not found.');
        }

        $normalizedPackageHash = preg_replace('/^sha256:/i', '', $packageHash) ?? $packageHash;

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

        DB::transaction(function () use (
            $importRunId,
            $workspaceId,
            $previewId,
            $sourceSystem,
            $preview,
            $packageHash,
            $clientCount,
            $actorUserId,
            $now,
            $occurredAt,
            $correlationId,
        ): void {
            $this->importRuns->insert([
                'id' => $importRunId,
                'workspace_id' => $workspaceId,
                'preview_id' => $previewId,
                'source_system' => $sourceSystem,
                'source_exported_at' => $preview['source_exported_at'],
                'package_hash' => $packageHash,
                'status' => 'Processing',
                'client_count' => $clientCount,
                'processed_count' => 0,
                'created_by' => $actorUserId,
                'created_at' => $now->format('Y-m-d H:i:sP'),
                'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);

            $event = new ClientHistoryImportRequested(
                importRunId: $importRunId,
                workspaceId: $workspaceId,
                sourceSystem: $sourceSystem,
                packageHash: $preview['package_hash'],
                clientCount: $clientCount,
                eventId: EventId::generate(),
                occurredAt: $occurredAt,
            );

            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));
        });

        ImportHistoricalClientsJob::dispatchAfterResponse(
            importRunId: $importRunId,
            workspaceId: $workspaceId,
            previewId: $previewId,
            preview: $preview,
            correlationId: $correlationId,
        );

        return [
            'import_run_id' => $importRunId,
            'workspace_id' => $workspaceId,
            'preview_id' => $previewId,
            'source_system' => $sourceSystem,
            'package_hash' => $packageHash,
            'status' => 'Processing',
            'client_count' => $clientCount,
            'processed_count' => 0,
            'correlation_id' => $correlationId,
            'request_id' => $requestId,
        ];
    }
}
