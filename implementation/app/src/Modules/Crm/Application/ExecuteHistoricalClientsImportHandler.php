<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\ClientHistoryImportCompleted;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientHistoryImportPreviewRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientHistoryImportRunRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class ExecuteHistoricalClientsImportHandler
{
    public function __construct(
        private readonly PostgresClientHistoryImportRunRepository $importRuns,
        private readonly PostgresClientHistoryImportPreviewRepository $previews,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(string $workspaceId, string $importRunId, ?string $correlationId = null): void
    {
        $run = $this->importRuns->findById($workspaceId, $importRunId);

        if ($run === null) {
            throw new \DomainException('Import run not found.');
        }

        if (($run['status'] ?? null) === 'Completed') {
            return;
        }

        $preview = $this->previews->findById($workspaceId, (string) $run['preview_id']);

        if ($preview === null) {
            throw new \DomainException('Preview not found.');
        }

        $records = $preview['records'] ?? [];
        $clientCount = max(0, (int) ($preview['valid_row_count'] ?? 0));
        $sourceSystem = (string) ($preview['source_system'] ?? $run['source_system']);
        $packageHash = (string) ($preview['package_hash'] ?? $run['package_hash']);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $processed = 0;

        foreach ($records as $record) {
            if (($record['validation_status'] ?? null) !== 'Valid') {
                continue;
            }

            $this->upsertHistoricalClient(
                workspaceId: $workspaceId,
                importRunId: $importRunId,
                sourceSystem: $sourceSystem,
                record: $record,
                importedAt: $now,
            );
            $processed++;
            $this->importRuns->updateProcessedCount($importRunId, $processed);
        }

        if ($clientCount === 0 || $processed < $clientCount) {
            throw new \DomainException('Import manifest incomplete.');
        }

        $this->importRuns->markCompleted($importRunId);

        $this->outbox->append(OutgoingMessage::fromDomainEvent(
            new ClientHistoryImportCompleted(
                importRunId: $importRunId,
                workspaceId: $workspaceId,
                sourceSystem: $sourceSystem,
                packageHash: $packageHash,
                clientCount: $clientCount,
                processedCount: $processed,
                eventId: EventId::generate(),
                occurredAt: $now,
            ),
            correlationId: $correlationId,
        ));
    }

    /** @param array<string, mixed> $record */
    private function upsertHistoricalClient(
        string $workspaceId,
        string $importRunId,
        string $sourceSystem,
        array $record,
        \DateTimeImmutable $importedAt,
    ): void {
        $externalId = (string) ($record['external_id'] ?? '');
        $canonicalHash = (string) ($record['canonical_record_hash'] ?? '');
        $existing = DB::table('crm.clients')
            ->where('workspace_id', $workspaceId)
            ->where('source_system', $sourceSystem)
            ->where('external_id', $externalId)
            ->first();

        if ($existing !== null) {
            if ((string) $existing->canonical_record_hash !== $canonicalHash) {
                throw new \DomainException('Conflict.');
            }

            return;
        }

        $profile = $record['profile'] ?? [];
        if (! isset($profile['display_name'])) {
            $profile['display_name'] = $externalId !== '' ? $externalId : 'Imported Client';
        }

        $status = ($record['status'] ?? 'Active') === 'Archived' ? 'Archived' : 'Active';
        $archivedAt = $status === 'Archived' ? $importedAt->format('Y-m-d H:i:sP') : null;
        $sourceCreatedAt = $record['source_created_at'] ?? null;

        DB::table('crm.clients')->insert([
            'id' => UuidGenerator::generate(),
            'workspace_id' => $workspaceId,
            'kind' => $record['kind'] ?? 'Individual',
            'status' => $status,
            'display_name' => $profile['display_name'],
            'profile' => json_encode($profile, JSON_THROW_ON_ERROR),
            'billing_profile' => json_encode([]),
            'profile_version' => 1,
            'billing_profile_version' => 0,
            'version' => 1,
            'created_at' => $importedAt->format('Y-m-d H:i:sP'),
            'updated_at' => $importedAt->format('Y-m-d H:i:sP'),
            'archive_reason' => $status === 'Archived' ? 'historical_import' : null,
            'archived_at' => $archivedAt,
            'source_system' => $sourceSystem,
            'external_id' => $externalId,
            'import_run_id' => $importRunId,
            'canonical_record_hash' => $canonicalHash,
            'source_created_at' => is_string($sourceCreatedAt) && $sourceCreatedAt !== ''
                ? (new \DateTimeImmutable($sourceCreatedAt))->format('Y-m-d H:i:sP')
                : null,
        ]);
    }
}
