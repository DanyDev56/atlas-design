<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Illuminate\Support\Facades\DB;

final class GetPaymentAnalyticsFactHandler
{
    public function __construct(
        private readonly PostgresInvoiceRepository $invoices,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $workspaceId,
        string $invoiceId,
        string $paymentId,
        int $aggregateVersion,
    ): array {
        $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId));

        if ($invoice === null || $invoice->version() !== $aggregateVersion) {
            throw new \DomainException('Invoice not found.');
        }

        $payment = DB::table('billing.payments')
            ->where('id', $paymentId)
            ->where('invoice_id', $invoiceId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($payment === null) {
            throw new \DomainException('Payment not found.');
        }

        $fact = [
            'workspace_id' => $workspaceId,
            'invoice_id' => $invoiceId,
            'payment_id' => $paymentId,
            'aggregate_version' => $aggregateVersion,
            'client_id' => $invoice->clientId(),
            'status' => 'Active',
            'amount_applied_cents' => (int) $payment->amount_cents,
            'currency_code' => $payment->currency,
            'received_at' => $payment->recorded_at,
            'reversed_at' => null,
        ];
        $fact['fact_hash'] = hash('sha256', json_encode($fact, JSON_THROW_ON_ERROR));

        return $fact;
    }
}
