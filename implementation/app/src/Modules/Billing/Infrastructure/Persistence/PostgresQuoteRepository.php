<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Infrastructure\Persistence;

use Atlas\Modules\Billing\Domain\Quote;
use Atlas\Modules\Billing\Domain\QuoteId;
use Illuminate\Support\Facades\DB;

final class PostgresQuoteRepository
{
    public function insert(Quote $quote): void
    {
        DB::table('billing.quotes')->insert([
            'id' => $quote->id()->value,
            'workspace_id' => $quote->workspaceId(),
            'client_id' => $quote->clientId(),
            'opportunity_id' => $quote->opportunityId(),
            'status' => $quote->status(),
            'lines' => json_encode($quote->lines(), JSON_THROW_ON_ERROR),
            'total_cents' => $quote->totalCents(),
            'currency' => $quote->currency(),
            'client_snapshot' => json_encode($quote->clientSnapshot(), JSON_THROW_ON_ERROR),
            'opportunity_snapshot' => $quote->opportunitySnapshot() !== null
                ? json_encode($quote->opportunitySnapshot(), JSON_THROW_ON_ERROR)
                : null,
            'version' => $quote->version(),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'valid_until' => now()->addDays(30)->toIso8601String(),
        ]);
    }

    public function update(Quote $quote): void
    {
        DB::table('billing.quotes')
            ->where('id', $quote->id()->value)
            ->update([
                'status' => $quote->status(),
                'lines' => json_encode($quote->lines(), JSON_THROW_ON_ERROR),
                'total_cents' => $quote->totalCents(),
                'version' => $quote->version(),
                'updated_at' => now()->toIso8601String(),
                'sent_at' => $quote->sentAt()?->format('Y-m-d H:i:sP'),
                'accepted_at' => $quote->acceptedAt()?->format('Y-m-d H:i:sP'),
            ]);
    }

    public function findById(string $workspaceId, QuoteId $id): ?Quote
    {
        $row = DB::table('billing.quotes')
            ->where('id', $id->value)
            ->where('workspace_id', $workspaceId)
            ->first();

        return $row !== null ? Quote::reconstitute((array) $row) : null;
    }
}
