<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Infrastructure\Persistence;

use Atlas\Modules\Billing\Domain\PaymentId;
use Illuminate\Support\Facades\DB;

final class PostgresPaymentRepository
{
    public function insert(
        PaymentId $paymentId,
        string $workspaceId,
        string $invoiceId,
        int $amountCents,
        string $currency,
        ?string $reference,
        \DateTimeImmutable $recordedAt,
    ): void {
        DB::table('billing.payments')->insert([
            'id' => $paymentId->value,
            'workspace_id' => $workspaceId,
            'invoice_id' => $invoiceId,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'reference' => $reference,
            'recorded_at' => $recordedAt->format('Y-m-d H:i:sP'),
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
