<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\Quote;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Illuminate\Support\Facades\DB;

final class GetQuoteAnalyticsFactHandler
{
    public function __construct(
        private readonly PostgresQuoteRepository $quotes,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $workspaceId, string $quoteId, int $aggregateVersion): array
    {
        $quote = $this->quotes->findById($workspaceId, new QuoteId($quoteId));

        if ($quote === null || $quote->version() !== $aggregateVersion) {
            throw new \DomainException('Quote not found.');
        }

        $row = DB::table('billing.quotes')->where('id', $quoteId)->first();

        $fact = [
            'workspace_id' => $workspaceId,
            'quote_id' => $quoteId,
            'aggregate_version' => $aggregateVersion,
            'client_id' => $quote->clientId(),
            'opportunity_id' => $quote->opportunityId(),
            'status' => $quote->status(),
            'gross_amount_cents' => $quote->totalCents(),
            'currency_code' => $quote->currency(),
            'created_at' => $row?->created_at,
            'sent_at' => $row?->sent_at,
            'responded_at' => $row?->accepted_at,
            'terminal_at' => $quote->status() === Quote::STATUS_ACCEPTED ? $row?->accepted_at : null,
            'valid_until' => $row?->valid_until,
        ];
        $fact['fact_hash'] = hash('sha256', json_encode($fact, JSON_THROW_ON_ERROR));

        return $fact;
    }
}
