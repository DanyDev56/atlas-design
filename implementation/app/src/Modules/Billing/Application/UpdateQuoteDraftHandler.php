<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\LineCalculator;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class UpdateQuoteDraftHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresQuoteRepository $quotes,
        private readonly PostgresBillingIdempotencyStore $idempotency,
    ) {}

    /** @param list<array<string, mixed>> $lines */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $quoteId,
        array $lines,
        int $expectedRevision,
        string $requestId,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.quotes.update-draft');

        $scope = 'billing.update_quote_draft';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $quoteId, $lines, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $quoteId, $lines, $expectedRevision,
            $requestId, $scope, $fingerprint,
        ): array {
            $quote = $this->quotes->findById($workspaceId, new QuoteId($quoteId));

            if ($quote === null) {
                throw new \DomainException('Quote not found.');
            }

            if ($quote->version() !== $expectedRevision) {
                throw new \DomainException('Quote version conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $quote->updateDraft($lines, LineCalculator::totalCents($lines), $now);
            $this->quotes->update($quote);

            $response = [
                'quote_id' => $quoteId,
                'status' => $quote->status(),
                'total_cents' => $quote->totalCents(),
                'version' => $quote->version(),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
