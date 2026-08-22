<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Infrastructure\Persistence;

use Atlas\Modules\Billing\Domain\Invoice;
use Atlas\Modules\Billing\Domain\InvoiceId;
use Illuminate\Support\Facades\DB;

final class PostgresInvoiceRepository
{
    public function insert(Invoice $invoice): void
    {
        DB::table('billing.invoices')->insert([
            'id' => $invoice->id()->value,
            'workspace_id' => $invoice->workspaceId(),
            'client_id' => $invoice->clientId(),
            'quote_id' => $invoice->quoteId(),
            'status' => $invoice->status(),
            'settlement_status' => $invoice->settlementStatus(),
            'invoice_number' => $invoice->invoiceNumber(),
            'lines' => json_encode($invoice->lines(), JSON_THROW_ON_ERROR),
            'total_cents' => $invoice->totalCents(),
            'balance_cents' => $invoice->balanceCents(),
            'currency' => $invoice->currency(),
            'client_snapshot' => json_encode($invoice->clientSnapshot(), JSON_THROW_ON_ERROR),
            'version' => $invoice->version(),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function update(Invoice $invoice): void
    {
        DB::table('billing.invoices')
            ->where('id', $invoice->id()->value)
            ->update([
                'status' => $invoice->status(),
                'settlement_status' => $invoice->settlementStatus(),
                'invoice_number' => $invoice->invoiceNumber(),
                'balance_cents' => $invoice->balanceCents(),
                'version' => $invoice->version(),
                'updated_at' => now()->toIso8601String(),
                'issued_at' => $invoice->issuedAt()?->format('Y-m-d H:i:sP'),
                'due_date' => $invoice->dueDate()?->format('Y-m-d H:i:sP'),
                'paid_at' => $invoice->paidAt()?->format('Y-m-d H:i:sP'),
                'sent_at' => $invoice->sentAt()?->format('Y-m-d H:i:sP'),
            ]);
    }

    public function findById(string $workspaceId, InvoiceId $id, bool $forUpdate = false): ?Invoice
    {
        $query = DB::table('billing.invoices')
            ->where('id', $id->value)
            ->where('workspace_id', $workspaceId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row !== null ? Invoice::reconstitute((array) $row) : null;
    }

    public function findByQuoteId(string $workspaceId, string $quoteId): ?Invoice
    {
        $row = DB::table('billing.invoices')
            ->where('workspace_id', $workspaceId)
            ->where('quote_id', $quoteId)
            ->first();

        return $row !== null ? Invoice::reconstitute((array) $row) : null;
    }

    public function nextInvoiceNumber(string $workspaceId): string
    {
        $count = (int) DB::table('billing.invoices')
            ->where('workspace_id', $workspaceId)
            ->where('status', Invoice::STATUS_ISSUED)
            ->where('is_historical_import', false)
            ->count();

        return sprintf('INV-%06d', $count + 1);
    }
}
