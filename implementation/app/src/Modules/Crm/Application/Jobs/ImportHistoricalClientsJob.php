<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application\Jobs;

use Atlas\Modules\Crm\Domain\ClientHistoryImportCompleted;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientHistoryImportRunRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ImportHistoricalClientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $importRunId,
        public readonly string $workspaceId,
        public readonly string $previewId,
        /** @var array<string, mixed> */
        public readonly array $preview,
        public readonly ?string $correlationId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(
        PostgresClientHistoryImportRunRepository $importRuns,
        OutboxWriter $outbox,
    ): void {
        $records = $this->preview['records'] ?? [];
        $clientCount = max(0, (int) ($this->preview['valid_row_count'] ?? 0));
        $now = now();
        $processed = 0;

        DB::transaction(function () use ($records, $clientCount, $now, &$processed, $importRuns, $outbox): void {
            foreach ($records as $record) {
                if (($record['validation_status'] ?? null) !== 'Valid') {
                    continue;
                }

                $profile = $record['profile'] ?? [];
                if (!isset($profile['display_name'])) {
                    $profile['display_name'] = $record['external_id'] ?? 'Imported Client';
                }

                $profile['historical_import'] = [
                    'import_run_id' => $this->importRunId,
                    'source_system' => $this->preview['source_system'] ?? '',
                    'external_id' => $record['external_id'] ?? '',
                    'canonical_record_hash' => $record['canonical_record_hash'] ?? '',
                    'source_created_at' => $record['source_created_at'] ?? '',
                    'imported_at' => $now->format(DATE_ATOM),
                ];

                DB::table('crm.clients')->insert([
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $this->workspaceId,
                    'kind' => $record['kind'] ?? 'Individual',
                    'status' => $record['status'] ?? 'Active',
                    'display_name' => $profile['display_name'] ?? '',
                    'profile' => json_encode($profile, JSON_THROW_ON_ERROR),
                    'billing_profile' => json_encode([]),
                    'profile_version' => 1,
                    'billing_profile_version' => 1,
                    'version' => 1,
                    'created_at' => $now->format('Y-m-d H:i:sP'),
                    'updated_at' => $now->format('Y-m-d H:i:sP'),
                ]);

                $processed++;
                $importRuns->updateProcessedCount($this->importRunId, $processed);
            }

            if ($clientCount > 0 && $processed >= $clientCount) {
                $importRuns->markCompleted($this->importRunId);

                $occurredAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $completedEvent = new ClientHistoryImportCompleted(
                    importRunId: $this->importRunId,
                    workspaceId: $this->workspaceId,
                    sourceSystem: (string) ($this->preview['source_system'] ?? ''),
                    packageHash: (string) ($this->preview['package_hash'] ?? ''),
                    clientCount: $clientCount,
                    processedCount: $processed,
                    eventId: EventId::generate(),
                    occurredAt: $occurredAt,
                );

                $outbox->append(OutgoingMessage::fromDomainEvent($completedEvent, correlationId: $this->correlationId));
            }
        });
    }
}
