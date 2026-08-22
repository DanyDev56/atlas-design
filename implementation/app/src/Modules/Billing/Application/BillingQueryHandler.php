<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\CreditNoteId;
use Atlas\Modules\Billing\Domain\Invoice;
use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresCreditNoteRepository;
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
        private readonly PostgresCreditNoteRepository $creditNotes,
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
                'original_number' => $row->original_number ?? null,
                'is_historical_import' => (bool) ($row->is_historical_import ?? false),
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

        $deposit = $this->invoices->findByQuoteIdAndKind($workspaceId, $quoteId, Invoice::KIND_DEPOSIT);
        $final = $this->invoices->findByQuoteIdAndKind($workspaceId, $quoteId, Invoice::KIND_FINAL);
        $provenance = DB::table('billing.quotes')->where('id', $quoteId)->first();
        $emailDelivery = $this->documentEmailDelivery(
            'billing.quote_delivery_requested',
            'quote_id',
            $quoteId,
        );

        return [
            'quote_id' => $quote->id()->value,
            'client_id' => $quote->clientId(),
            'opportunity_id' => $quote->opportunityId(),
            'status' => $quote->status(),
            'lines' => $quote->lines(),
            'total_cents' => $quote->totalCents(),
            'currency' => $quote->currency(),
            'version' => $quote->version(),
            'invoice_id' => $final?->id()->value ?? $deposit?->id()->value,
            'deposit_invoice_id' => $deposit?->id()->value,
            'final_invoice_id' => $final?->id()->value,
            'deposit_invoice_status' => $deposit?->status(),
            'original_number' => $quote->originalNumber(),
            'is_historical_import' => $quote->isHistoricalImport(),
            'source_system' => $provenance->source_system ?? null,
            'external_id' => $provenance->external_id ?? null,
            'email_delivery_status' => $emailDelivery['status'] ?? null,
            'email_delivery_updated_at' => $emailDelivery['updated_at'] ?? null,
        ];
    }

    /** @return array{status: string, updated_at: mixed}|null */
    private function documentEmailDelivery(string $eventType, string $payloadKey, string $documentId): ?array
    {
        $row = DB::table('platform.outbox_messages as outbox')
            ->leftJoin('platform.email_deliveries as delivery', 'delivery.event_id', '=', 'outbox.event_id')
            ->where('outbox.event_type', $eventType)
            ->whereRaw("outbox.payload->>'{$payloadKey}' = ?", [$documentId])
            ->orderByDesc('outbox.created_at')
            ->first([
                'delivery.status as delivery_status',
                'delivery.updated_at as delivery_updated_at',
                'outbox.attempts',
                'outbox.failed_at',
                'outbox.created_at',
            ]);

        if ($row === null) {
            return null;
        }

        $status = is_string($row->delivery_status)
            ? $row->delivery_status
            : ($row->failed_at !== null ? 'Failed' : ((int) $row->attempts > 0 ? 'Retrying' : 'Pending'));

        return [
            'status' => $status,
            'updated_at' => $row->delivery_updated_at ?? $row->created_at,
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

        $provenance = DB::table('billing.invoices')->where('id', $invoiceId)->first();
        $emailDelivery = $this->documentEmailDelivery(
            'billing.invoice_delivery_requested',
            'invoice_id',
            $invoiceId,
        );
        $payments = DB::table('billing.payments')
            ->where('workspace_id', $workspaceId)
            ->where('invoice_id', $invoiceId)
            ->orderBy('recorded_at')
            ->get()
            ->map(fn ($row): array => [
                'payment_id' => $row->id,
                'amount_cents' => (int) $row->amount_cents,
                'currency' => $row->currency,
                'recorded_at' => $row->recorded_at,
                'is_historical_import' => (bool) $row->is_historical_import,
                'external_id' => $row->external_id,
                'amount_received_cents' => $row->amount_received_cents !== null ? (int) $row->amount_received_cents : null,
                'amount_applied_cents' => $row->amount_applied_cents !== null ? (int) $row->amount_applied_cents : null,
            ])
            ->all();

        return [
            'invoice_id' => $invoice->id()->value,
            'client_id' => $invoice->clientId(),
            'quote_id' => $invoice->quoteId(),
            'kind' => $invoice->kind(),
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
            'last_reminded_at' => $invoice->lastRemindedAt()?->format(DATE_ATOM),
            'reminder_count' => $invoice->reminderCount(),
            'overdue' => $invoice->isOverdue(),
            'overdue_at' => $invoice->overdueAt()?->format(DATE_ATOM),
            'is_historical_import' => $invoice->isHistoricalImport(),
            'original_number' => $invoice->originalNumber(),
            'source_system' => $provenance->source_system ?? null,
            'external_id' => $provenance->external_id ?? null,
            'payments' => $payments,
            'credit_notes' => $this->creditNotes->listForInvoice($workspaceId, $invoiceId),
            'email_delivery_status' => $emailDelivery['status'] ?? null,
            'email_delivery_updated_at' => $emailDelivery['updated_at'] ?? null,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listCreditNotes(string $actorUserId, string $workspaceId, string $invoiceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.credit-notes.read');

        return $this->creditNotes->listForInvoice($workspaceId, $invoiceId);
    }

    /** @return array<string, mixed> */
    public function getCreditNote(string $actorUserId, string $workspaceId, string $creditNoteId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.credit-notes.read');
        $creditNote = $this->creditNotes->findById($workspaceId, new CreditNoteId($creditNoteId));
        if ($creditNote === null) {
            throw new \DomainException('Credit note not found.');
        }

        $provenance = DB::table('billing.credit_notes')
            ->where('workspace_id', $workspaceId)
            ->where('id', $creditNoteId)
            ->first();

        return [
            'credit_note_id' => $creditNote->id()->value,
            'invoice_id' => $creditNote->invoiceId(),
            'status' => $creditNote->status(),
            'credit_note_number' => $creditNote->number(),
            'original_number' => $provenance->original_number ?? null,
            'net_amount_cents' => $provenance->net_amount_cents !== null ? (int) $provenance->net_amount_cents : null,
            'tax_amount_cents' => $provenance->tax_amount_cents !== null ? (int) $provenance->tax_amount_cents : null,
            'gross_amount_cents' => $provenance->gross_amount_cents !== null ? (int) $provenance->gross_amount_cents : null,
            'lines' => $creditNote->lines(),
            'total_cents' => $creditNote->totalCents(),
            'amount_applied_cents' => $creditNote->amountAppliedCents(),
            'unapplied_amount_cents' => $creditNote->unappliedAmountCents(),
            'remainder_disposition' => $creditNote->remainderDisposition(),
            'currency' => $creditNote->currency(),
            'reason' => $creditNote->reason(),
            'version' => $creditNote->version(),
            'issued_at' => $creditNote->issuedAt()?->format(DATE_ATOM),
            'applied_at' => $creditNote->appliedAt()?->format(DATE_ATOM),
            'is_historical_import' => (bool) ($provenance->is_historical_import ?? false),
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
                'kind' => $row->kind ?? 'Final',
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
                'overdue' => $row->overdue_at !== null && (int) $row->balance_cents > 0,
                'overdue_at' => $row->overdue_at,
                'is_historical_import' => (bool) $row->is_historical_import,
                'original_number' => $row->original_number,
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
                'overdue' => $row->overdue_at !== null && (int) $row->balance_cents > 0,
            ])
            ->all();
    }
}
