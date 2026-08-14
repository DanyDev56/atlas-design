<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class BillingQueryHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresQuoteRepository $quotes,
        private readonly PostgresInvoiceRepository $invoices,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listQuotes(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.quotes.read');

        return DB::table('billing.quotes')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => [
                'quote_id' => $row->id,
                'client_id' => $row->client_id,
                'opportunity_id' => $row->opportunity_id,
                'status' => $row->status,
                'total_cents' => (int) $row->total_cents,
                'currency' => $row->currency,
                'version' => (int) $row->version,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function getQuote(string $actorUserId, string $workspaceId, string $quoteId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.quotes.read');

        $quote = $this->quotes->findById($workspaceId, new QuoteId($quoteId));

        if ($quote === null) {
            throw new \DomainException('Quote not found.');
        }

        $invoice = $this->invoices->findByQuoteId($workspaceId, $quoteId);

        return [
            'quote_id' => $quote->id()->value,
            'client_id' => $quote->clientId(),
            'opportunity_id' => $quote->opportunityId(),
            'status' => $quote->status(),
            'lines' => $quote->lines(),
            'total_cents' => $quote->totalCents(),
            'currency' => $quote->currency(),
            'version' => $quote->version(),
            'invoice_id' => $invoice?->id()->value,
        ];
    }

    /** @return array<string, mixed> */
    public function getInvoice(string $actorUserId, string $workspaceId, string $invoiceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.invoices.read');

        $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId));

        if ($invoice === null) {
            throw new \DomainException('Invoice not found.');
        }

        return [
            'invoice_id' => $invoice->id()->value,
            'client_id' => $invoice->clientId(),
            'quote_id' => $invoice->quoteId(),
            'status' => $invoice->status(),
            'settlement_status' => $invoice->settlementStatus(),
            'invoice_number' => $invoice->invoiceNumber(),
            'lines' => $invoice->lines(),
            'total_cents' => $invoice->totalCents(),
            'balance_cents' => $invoice->balanceCents(),
            'currency' => $invoice->currency(),
            'version' => $invoice->version(),
            'issued_at' => $invoice->issuedAt()?->format(DATE_ATOM),
            'sent_at' => $invoice->sentAt()?->format(DATE_ATOM),
            'due_date' => $invoice->dueDate()?->format(DATE_ATOM),
            'paid_at' => $invoice->paidAt()?->format(DATE_ATOM),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listInvoices(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.invoices.read');

        return DB::table('billing.invoices')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => [
                'invoice_id' => $row->id,
                'client_id' => $row->client_id,
                'quote_id' => $row->quote_id,
                'status' => $row->status,
                'settlement_status' => $row->settlement_status,
                'invoice_number' => $row->invoice_number,
                'total_cents' => (int) $row->total_cents,
                'balance_cents' => (int) $row->balance_cents,
                'currency' => $row->currency,
                'version' => (int) $row->version,
                'issued_at' => $row->issued_at,
                'sent_at' => $row->sent_at,
                'due_date' => $row->due_date,
                'paid_at' => $row->paid_at,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function listRecentInvoices(string $actorUserId, string $workspaceId, int $limit = 5): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.invoices.read');

        return DB::table('billing.invoices')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'invoice_id' => $row->id,
                'invoice_number' => $row->invoice_number,
                'status' => $row->status,
                'settlement_status' => $row->settlement_status,
                'total_cents' => (int) $row->total_cents,
                'balance_cents' => (int) $row->balance_cents,
                'currency' => $row->currency,
            ])
            ->all();
    }
}
