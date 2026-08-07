<?php

declare(strict_types=1);

namespace Atlas\Composition\Billing;

use Atlas\Modules\Billing\Domain\Quote;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Modules\Crm\Domain\Opportunity;
use Atlas\Modules\Crm\Domain\OpportunityId;
use Atlas\Modules\Crm\Domain\OpportunityWon;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Illuminate\Support\Facades\DB;

final class WinOpportunityFromQuoteHandler
{
    public function __construct(
        private readonly PostgresQuoteRepository $quotes,
        private readonly PostgresOpportunityRepository $opportunities,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed>|null */
    public function handle(
        string $workspaceId,
        string $quoteId,
        ?string $opportunityId,
        ?string $correlationId = null,
    ): ?array {
        if ($opportunityId === null) {
            return null;
        }

        $requestId = hash('sha256', $quoteId.'|'.$opportunityId);
        $scope = 'crm.win_opportunity_from_quote';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $quoteId, $opportunityId,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $quoteId, $opportunityId,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $quote = $this->quotes->findById($workspaceId, new QuoteId($quoteId));

            if ($quote === null || $quote->status() !== Quote::STATUS_ACCEPTED) {
                throw new \DomainException('Quote not accepted.');
            }

            if ($quote->opportunityId() !== $opportunityId) {
                throw new \DomainException('Reference conflict.');
            }

            $opportunity = $this->opportunities->findById($workspaceId, new OpportunityId($opportunityId));

            if ($opportunity === null) {
                throw new \DomainException('Opportunity not found.');
            }

            if ($opportunity->status() === Opportunity::STATUS_WON) {
                return [
                    'opportunity_id' => $opportunityId,
                    'status' => $opportunity->status(),
                    'version' => $opportunity->version(),
                    'quote_id' => $quoteId,
                ];
            }

            if ($opportunity->status() !== Opportunity::STATUS_QUALIFIED) {
                throw new \DomainException('Opportunity is not qualified.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $opportunity->win($now);

            $event = new OpportunityWon(
                opportunityId: new OpportunityId($opportunityId),
                workspaceId: $workspaceId,
                quoteId: $quoteId,
                eventId: EventId::generate(),
                occurredAt: $now,
            );

            $this->opportunities->update($opportunity);
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'opportunity_id' => $opportunityId,
                'status' => $opportunity->status(),
                'version' => $opportunity->version(),
                'quote_id' => $quoteId,
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
