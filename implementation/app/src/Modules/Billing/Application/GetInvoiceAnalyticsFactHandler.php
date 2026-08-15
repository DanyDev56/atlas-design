<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Illuminate\Support\Facades\DB;

final class GetInvoiceAnalyticsFactHandler
{
    public function __construct(
        private readonly PostgresInvoiceRepository $invoices,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $workspaceId, string $invoiceId, int $aggregateVersion): array
    {
        $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId));

        if ($invoice === null || $invoice->version() !== $aggregateVersion) {
            throw new \DomainException('Invoice not found.');
        }

        $row = DB::table('billing.invoices')->where('id', $invoiceId)->first();

        $fact = [
            'workspace_id' => $workspaceId,
            'invoice_id' => $invoiceId,
            'aggregate_version' => $aggregateVersion,
            'client_id' => $invoice->clientId(),
            'kind' => 'Final',
            'document_status' => $invoice->status(),
            'gross_amount_cents' => $invoice->totalCents(),
            'currency_code' => $invoice->currency(),
            'issued_at' => $row?->issued_at,
            'due_date' => $row?->due_date,
            'outstanding_balance_cents' => $invoice->balanceCents(),
            'settlement_status' => $invoice->settlementStatus(),
            'paid_at' => $row?->paid_at,
        ];
        $fact['fact_hash'] = hash('sha256', json_encode($fact, JSON_THROW_ON_ERROR));

        return $fact;
    }
}
