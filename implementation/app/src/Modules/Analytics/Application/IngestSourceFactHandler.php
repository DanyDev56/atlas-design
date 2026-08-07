<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Application;

use Atlas\Modules\Analytics\Domain\AnalyticsFactRecorded;
use Atlas\Modules\Analytics\Infrastructure\Persistence\PostgresAnalyticsFactRepository;
use Atlas\Modules\Billing\Application\GetInvoiceAnalyticsFactHandler;
use Atlas\Modules\Billing\Application\GetPaymentAnalyticsFactHandler;
use Atlas\Modules\Billing\Application\GetQuoteAnalyticsFactHandler;
use Atlas\Modules\Crm\Application\GetOpportunityAnalyticsFactHandler;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Illuminate\Support\Facades\DB;

final class IngestSourceFactHandler
{
    private const SUPPORTED = [
        'crm.opportunity_created',
        'crm.opportunity_qualified',
        'crm.opportunity_won',
        'billing.quote_sent',
        'billing.quote_accepted',
        'billing.invoice_issued',
        'billing.payment_recorded',
    ];

    public function __construct(
        private readonly PostgresAnalyticsFactRepository $facts,
        private readonly GetOpportunityAnalyticsFactHandler $opportunityFacts,
        private readonly GetQuoteAnalyticsFactHandler $quoteFacts,
        private readonly GetInvoiceAnalyticsFactHandler $invoiceFacts,
        private readonly GetPaymentAnalyticsFactHandler $paymentFacts,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed>|null */
    public function handle(OutgoingMessage $message): ?array
    {
        if (! in_array($message->eventType, self::SUPPORTED, true)) {
            return null;
        }

        $payload = $message->payload;
        $workspaceId = $payload['workspace_id'] ?? null;

        if ($workspaceId === null) {
            throw new \DomainException('Unsupported source event.');
        }

        $sourceEventId = $message->eventId->value;

        if ($this->facts->exists($workspaceId, $sourceEventId)) {
            return ['source_event_id' => $sourceEventId, 'status' => 'already_ingested'];
        }

        [$aggregateType, $aggregateId, $aggregateVersion, $fact, $sourceKind] = $this->resolveFact(
            $message->eventType,
            $workspaceId,
            $payload,
        );

        $this->facts->insert(
            workspaceId: $workspaceId,
            sourceEventId: $sourceEventId,
            sourceEventType: $message->eventType,
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            aggregateVersion: $aggregateVersion,
            factHash: $fact['fact_hash'],
            payload: $fact,
            sourceOccurredAt: $message->occurredAt,
        );

        $this->facts->updateWatermark($workspaceId, $sourceKind, $message->occurredAt);

        $event = new AnalyticsFactRecorded(
            workspaceId: $workspaceId,
            sourceEventId: $sourceEventId,
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            eventId: EventId::generate(),
            occurredAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $message->correlationId));

        return [
            'source_event_id' => $sourceEventId,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'status' => 'ingested',
        ];
    }

    /** @return array{0: string, 1: string, 2: int, 3: array<string, mixed>, 4: string} */
    private function resolveFact(string $eventType, string $workspaceId, array $payload): array
    {
        return match ($eventType) {
            'crm.opportunity_created', 'crm.opportunity_qualified', 'crm.opportunity_won' => (function () use ($workspaceId, $payload): array {
                $opportunityId = $payload['opportunity_id'];
                $version = (int) DB::table('crm.opportunities')->where('id', $opportunityId)->value('version');
                $fact = $this->opportunityFacts->handle($workspaceId, $opportunityId, $version);

                return ['opportunity', $opportunityId, $version, $fact, 'crm'];
            })(),
            'billing.quote_sent', 'billing.quote_accepted' => (function () use ($workspaceId, $payload): array {
                $quoteId = $payload['quote_id'];
                $version = (int) DB::table('billing.quotes')->where('id', $quoteId)->value('version');
                $fact = $this->quoteFacts->handle($workspaceId, $quoteId, $version);

                return ['quote', $quoteId, $version, $fact, 'billing'];
            })(),
            'billing.invoice_issued' => (function () use ($workspaceId, $payload): array {
                $invoiceId = $payload['invoice_id'];
                $version = (int) DB::table('billing.invoices')->where('id', $invoiceId)->value('version');
                $fact = $this->invoiceFacts->handle($workspaceId, $invoiceId, $version);

                return ['invoice', $invoiceId, $version, $fact, 'billing'];
            })(),
            'billing.payment_recorded' => (function () use ($workspaceId, $payload): array {
                $invoiceId = $payload['invoice_id'];
                $paymentId = $payload['payment_id'];
                $version = (int) DB::table('billing.invoices')->where('id', $invoiceId)->value('version');
                $fact = $this->paymentFacts->handle($workspaceId, $invoiceId, $paymentId, $version);

                return ['payment', $paymentId, $version, $fact, 'billing'];
            })(),
            default => throw new \DomainException('Unsupported source event.'),
        };
    }
}
