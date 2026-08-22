<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Application;

use Atlas\Modules\Analytics\Domain\AnalyticsFactRecorded;
use Atlas\Modules\Analytics\Domain\AnalyticsProjectionRebuilt;
use Atlas\Modules\Analytics\Domain\AnalyticsProjectionRebuildStarted;
use Atlas\Modules\Analytics\Infrastructure\Persistence\PostgresAnalyticsFactRepository;
use Atlas\Modules\Analytics\Infrastructure\Persistence\PostgresHistoricalImportRebuildRepository;
use Atlas\Modules\Analytics\Infrastructure\PostgresAnalyticsIdempotencyStore;
use Atlas\Modules\Billing\Application\GetInvoiceAnalyticsFactHandler;
use Atlas\Modules\Billing\Application\GetPaymentAnalyticsFactHandler;
use Atlas\Modules\Billing\Application\GetQuoteAnalyticsFactHandler;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class RebuildAnalyticsAfterHistoricalImportHandler
{
    public function __construct(
        private readonly PostgresHistoricalImportRebuildRepository $rebuilds,
        private readonly PostgresAnalyticsFactRepository $facts,
        private readonly GetQuoteAnalyticsFactHandler $quoteFacts,
        private readonly GetInvoiceAnalyticsFactHandler $invoiceFacts,
        private readonly GetPaymentAnalyticsFactHandler $paymentFacts,
        private readonly PublishAnalyticsSnapshotHandler $publishSnapshot,
        private readonly PostgresAnalyticsIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handleCompletion(string $kind, array $payload, ?string $correlationId = null): void
    {
        $workspaceId = (string) ($payload['workspace_id'] ?? '');
        $sourceSystem = (string) ($payload['source_system'] ?? '');
        if ($workspaceId === '' || $sourceSystem === '') {
            return;
        }

        $this->rebuilds->recordCompletion($kind, $payload, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $crm = $this->rebuilds->findCompletion($workspaceId, $sourceSystem, 'crm');
        $billing = $this->rebuilds->findCompletion($workspaceId, $sourceSystem, 'billing');
        if ($crm === null || $billing === null) {
            return;
        }

        $this->rebuild(
            workspaceId: $workspaceId,
            sourceSystem: $sourceSystem,
            crmRunId: (string) $crm['import_run_id'],
            billingRunId: (string) $billing['import_run_id'],
            correlationId: $correlationId,
        );
    }

    public function rebuild(
        string $workspaceId,
        string $sourceSystem,
        string $crmRunId,
        string $billingRunId,
        ?string $correlationId = null,
    ): void {
        $existing = $this->rebuilds->findRebuild($workspaceId, $crmRunId, $billingRunId);
        if ($existing !== null && ($existing['status'] ?? null) === 'Completed') {
            return;
        }

        $scope = 'analytics.rebuild_historical_import';
        $requestId = $crmRunId.'|'.$billingRunId;
        $fingerprint = hash('sha256', json_encode([$workspaceId, $sourceSystem, $crmRunId, $billingRunId], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);
        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Conflict.');
            }

            return;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $generationUuid = $existing['generation_uuid'] ?? UuidGenerator::generate();
        $generationId = (int) ($existing['generation_id'] ?? $this->rebuilds->nextGenerationId($workspaceId));
        $rebuildId = $existing['id'] ?? UuidGenerator::generate();

        if ($existing === null) {
            $this->rebuilds->insertGeneration([
                'id' => $generationUuid,
                'workspace_id' => $workspaceId,
                'generation_id' => $generationId,
                'status' => 'Building',
                'rebuild_reason' => 'HistoricalImport',
                'crm_import_run_id' => $crmRunId,
                'billing_import_run_id' => $billingRunId,
                'started_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            $this->rebuilds->insertRebuild([
                'id' => $rebuildId,
                'workspace_id' => $workspaceId,
                'source_system' => $sourceSystem,
                'crm_import_run_id' => $crmRunId,
                'billing_import_run_id' => $billingRunId,
                'generation_uuid' => $generationUuid,
                'generation_id' => $generationId,
                'status' => 'Building',
                'quote_fact_count' => 0,
                'invoice_fact_count' => 0,
                'payment_fact_count' => 0,
                'created_at' => $now->format('Y-m-d H:i:sP'),
                'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            $this->outbox->append(OutgoingMessage::fromDomainEvent(new AnalyticsProjectionRebuildStarted(
                $workspaceId, $generationUuid, $generationId, 'HistoricalImport', EventId::generate(), $now,
            ), correlationId: $correlationId));
        }

        $quotes = $this->ingestQuotes($workspaceId, $billingRunId, $correlationId);
        $invoices = $this->ingestInvoices($workspaceId, $billingRunId, $correlationId);
        $payments = $this->ingestPayments($workspaceId, $billingRunId, $correlationId);

        $expectedQuotes = (int) DB::table('billing.quotes')->where('workspace_id', $workspaceId)->where('import_run_id', $billingRunId)->count();
        $expectedInvoices = (int) DB::table('billing.invoices')->where('workspace_id', $workspaceId)->where('import_run_id', $billingRunId)->count();
        $expectedPayments = (int) DB::table('billing.payments')->where('workspace_id', $workspaceId)->where('import_run_id', $billingRunId)->count();
        if ($quotes !== $expectedQuotes || $invoices !== $expectedInvoices || $payments !== $expectedPayments) {
            throw new \DomainException('Import manifest incomplete.');
        }

        $this->facts->updateWatermark($workspaceId, 'crm', $now);
        $this->facts->updateWatermark($workspaceId, 'billing', $now);
        $this->rebuilds->activateGeneration($workspaceId, $generationUuid, $now);
        $actorUserId = (string) DB::table('billing.history_import_runs')->where('id', $billingRunId)->value('created_by');
        if ($actorUserId !== '') {
            $this->publishSnapshot->handle(
                actorUserId: $actorUserId,
                workspaceId: $workspaceId,
                requestId: 'historical-rebuild:'.$rebuildId,
                correlationId: $correlationId,
            );
        }
        $this->rebuilds->updateRebuild($rebuildId, [
            'status' => 'Completed',
            'quote_fact_count' => $quotes,
            'invoice_fact_count' => $invoices,
            'payment_fact_count' => $payments,
        ]);
        $this->outbox->append(OutgoingMessage::fromDomainEvent(new AnalyticsProjectionRebuilt(
            $workspaceId, $generationUuid, $generationId, 'HistoricalImport', EventId::generate(), $now,
        ), correlationId: $correlationId));

        $this->idempotency->store($scope, $requestId, $fingerprint, [
            'generation_id' => $generationId,
            'status' => 'Completed',
        ]);
    }

    private function ingestQuotes(string $workspaceId, string $billingRunId, ?string $correlationId): int
    {
        $count = 0;
        foreach (DB::table('billing.quotes')->where('workspace_id', $workspaceId)->where('import_run_id', $billingRunId)->get() as $row) {
            $occurredAt = new \DateTimeImmutable((string) ($row->sent_at ?? $row->created_at));
            $this->ingest(
                workspaceId: $workspaceId,
                sourceName: $billingRunId.':quote:'.$row->id,
                sourceEventType: 'analytics.historical_import.quote',
                aggregateType: 'quote',
                aggregateId: (string) $row->id,
                fact: $this->quoteFacts->handle($workspaceId, (string) $row->id, (int) $row->version),
                sourceKind: 'billing',
                occurredAt: $occurredAt,
                correlationId: $correlationId,
            );
            $count++;
        }

        return $count;
    }

    private function ingestInvoices(string $workspaceId, string $billingRunId, ?string $correlationId): int
    {
        $count = 0;
        foreach (DB::table('billing.invoices')->where('workspace_id', $workspaceId)->where('import_run_id', $billingRunId)->get() as $row) {
            $this->ingest(
                workspaceId: $workspaceId,
                sourceName: $billingRunId.':invoice:'.$row->id,
                sourceEventType: 'analytics.historical_import.invoice',
                aggregateType: 'invoice',
                aggregateId: (string) $row->id,
                fact: $this->invoiceFacts->handle($workspaceId, (string) $row->id, (int) $row->version),
                sourceKind: 'billing',
                occurredAt: new \DateTimeImmutable((string) ($row->issued_at ?? $row->created_at)),
                correlationId: $correlationId,
            );
            $count++;
        }

        return $count;
    }

    private function ingestPayments(string $workspaceId, string $billingRunId, ?string $correlationId): int
    {
        $count = 0;
        foreach (DB::table('billing.payments')->where('workspace_id', $workspaceId)->where('import_run_id', $billingRunId)->get() as $row) {
            $this->ingest(
                workspaceId: $workspaceId,
                sourceName: $billingRunId.':payment:'.$row->id,
                sourceEventType: 'analytics.historical_import.payment',
                aggregateType: 'payment',
                aggregateId: (string) $row->id,
                fact: $this->paymentFacts->handle($workspaceId, (string) $row->invoice_id, (string) $row->id, (int) DB::table('billing.invoices')->where('id', $row->invoice_id)->value('version')),
                sourceKind: 'billing',
                occurredAt: new \DateTimeImmutable((string) $row->recorded_at),
                correlationId: $correlationId,
            );
            $count++;
        }

        return $count;
    }

    /** @param array<string, mixed> $fact */
    private function ingest(
        string $workspaceId,
        string $sourceName,
        string $sourceEventType,
        string $aggregateType,
        string $aggregateId,
        array $fact,
        string $sourceKind,
        \DateTimeImmutable $occurredAt,
        ?string $correlationId,
    ): void {
        $sourceEventId = UuidGenerator::fromName($sourceName);
        if ($this->facts->exists($workspaceId, $sourceEventId)) {
            return;
        }

        $this->facts->insert(
            workspaceId: $workspaceId,
            sourceEventId: $sourceEventId,
            sourceEventType: $sourceEventType,
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            aggregateVersion: (int) $fact['aggregate_version'],
            factHash: (string) $fact['fact_hash'],
            payload: $fact,
            sourceOccurredAt: $occurredAt,
        );
        $this->facts->updateWatermark($workspaceId, $sourceKind, $occurredAt);
        $this->outbox->append(OutgoingMessage::fromDomainEvent(new AnalyticsFactRecorded(
            $workspaceId, $sourceEventId, $aggregateType, $aggregateId, EventId::generate(), new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        ), correlationId: $correlationId));
    }
}
