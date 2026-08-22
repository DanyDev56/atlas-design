<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\Invoice;
use Atlas\Modules\Billing\Domain\InvoiceCreated;
use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Domain\Quote;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class CreateDepositInvoiceFromQuoteHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresQuoteRepository $quotes,
        private readonly PostgresInvoiceRepository $invoices,
        private readonly PostgresBillingIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $quoteId,
        int $amountCents,
        int $expectedQuoteRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.invoices.create');

        $scope = 'billing.create_deposit_invoice_from_quote';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $quoteId, $amountCents, $expectedQuoteRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $quoteId, $amountCents, $expectedQuoteRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $existing = $this->invoices->findByQuoteIdAndKind(
                $workspaceId,
                $quoteId,
                Invoice::KIND_DEPOSIT,
                true,
            );
            if ($existing !== null) {
                return $this->serialize($existing);
            }

            if ($this->invoices->findByQuoteIdAndKind($workspaceId, $quoteId, Invoice::KIND_FINAL, true) !== null) {
                throw new \DomainException('Final invoice already exists.');
            }

            $quote = $this->quotes->findById($workspaceId, new QuoteId($quoteId));
            if ($quote === null) {
                throw new \DomainException('Quote not found.');
            }
            if ($quote->isHistoricalImport()) {
                throw new \DomainException('Historical imports are read-only.');
            }
            if ($quote->status() !== Quote::STATUS_ACCEPTED) {
                throw new \DomainException('Quote is not accepted.');
            }
            if ($quote->version() !== $expectedQuoteRevision) {
                throw new \DomainException('Quote version conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $invoice = Invoice::createDraftFromQuote(
                InvoiceId::generate(),
                $quote,
                $now,
                Invoice::KIND_DEPOSIT,
                $amountCents,
            );
            $this->invoices->insert($invoice);
            $this->outbox->append(OutgoingMessage::fromDomainEvent(new InvoiceCreated(
                invoiceId: $invoice->id(),
                workspaceId: $workspaceId,
                kind: $invoice->kind(),
                quoteId: $quoteId,
                eventId: EventId::generate(),
                occurredAt: $now,
            ), correlationId: $correlationId));

            $response = $this->serialize($invoice);
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }

    /** @return array<string, mixed> */
    private function serialize(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id()->value,
            'quote_id' => $invoice->quoteId(),
            'kind' => $invoice->kind(),
            'status' => $invoice->status(),
            'settlement_status' => $invoice->settlementStatus(),
            'total_cents' => $invoice->totalCents(),
            'balance_cents' => $invoice->balanceCents(),
            'currency' => $invoice->currency(),
            'version' => $invoice->version(),
        ];
    }
}
