<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\Invoice;
use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Domain\Quote;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class CreateFinalInvoiceFromQuoteHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresQuoteRepository $quotes,
        private readonly PostgresInvoiceRepository $invoices,
        private readonly PostgresBillingIdempotencyStore $idempotency,
    ) {}

    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $quoteId,
        string $requestId,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.invoices.create');

        $scope = 'billing.create_final_invoice_from_quote';
        $fingerprint = hash('sha256', json_encode([$workspaceId, $quoteId], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $quoteId, $requestId, $scope, $fingerprint,
        ): array {
            $existing = $this->invoices->findByQuoteId($workspaceId, $quoteId);

            if ($existing !== null) {
                return $this->serialize($existing);
            }

            $quote = $this->quotes->findById($workspaceId, new QuoteId($quoteId));

            if ($quote === null) {
                throw new \DomainException('Quote not found.');
            }

            if ($quote->status() !== Quote::STATUS_ACCEPTED) {
                throw new \DomainException('Quote is not accepted.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $invoiceId = InvoiceId::generate();
            $invoice = Invoice::createDraftFromQuote($invoiceId, $quote, $now);
            $this->invoices->insert($invoice);

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
            'status' => $invoice->status(),
            'settlement_status' => $invoice->settlementStatus(),
            'total_cents' => $invoice->totalCents(),
            'balance_cents' => $invoice->balanceCents(),
            'currency' => $invoice->currency(),
            'version' => $invoice->version(),
        ];
    }
}
