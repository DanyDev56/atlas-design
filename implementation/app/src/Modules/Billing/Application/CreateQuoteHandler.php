<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\LineCalculator;
use Atlas\Modules\Billing\Domain\Quote;
use Atlas\Modules\Billing\Domain\QuoteCreated;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Modules\Crm\Application\CrmQueryHandler;
use Atlas\Modules\Crm\Domain\Opportunity;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class CreateQuoteHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly CrmQueryHandler $crmQueries,
        private readonly PostgresQuoteRepository $quotes,
        private readonly PostgresBillingIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @param list<array<string, mixed>> $lines */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $clientId,
        ?string $opportunityId,
        array $lines,
        string $currency,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.quotes.create');

        $scope = 'billing.create_quote';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $clientId, $opportunityId, $lines, $currency,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $actorUserId, $workspaceId, $clientId, $opportunityId, $lines, $currency,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $clientContext = $this->crmQueries->getClientBillingContext($actorUserId, $workspaceId, $clientId);

            if ($clientContext['client_status'] !== 'Active') {
                throw new \DomainException('Client not found.');
            }

            $opportunitySnapshot = null;

            if ($opportunityId !== null) {
                $commercial = $this->crmQueries->getOpportunityCommercialContext(
                    $actorUserId,
                    $workspaceId,
                    $opportunityId,
                );

                if ($commercial['client_id'] !== $clientId) {
                    throw new \DomainException('Reference conflict.');
                }

                if ($commercial['opportunity_status'] !== Opportunity::STATUS_QUALIFIED) {
                    throw new \DomainException('Opportunity is not qualified.');
                }

                $opportunitySnapshot = $commercial;
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $totalCents = LineCalculator::totalCents($lines);
            $quoteId = QuoteId::generate();
            $clientSnapshot = [
                'client_id' => $clientContext['client_id'],
                'display_name' => $clientContext['current_display_name'],
                'billing_profile' => $clientContext['current_billing_profile'],
                'profile_version' => $clientContext['client_profile_version'],
                'billing_profile_version' => $clientContext['client_billing_profile_version'],
                'captured_at' => $now->format(DATE_ATOM),
            ];

            $quote = Quote::createDraft(
                id: $quoteId,
                workspaceId: $workspaceId,
                clientId: $clientId,
                opportunityId: $opportunityId,
                lines: $lines,
                totalCents: $totalCents,
                currency: strtoupper($currency),
                clientSnapshot: $clientSnapshot,
                opportunitySnapshot: $opportunitySnapshot,
                now: $now,
            );

            $event = new QuoteCreated(
                quoteId: $quoteId,
                workspaceId: $workspaceId,
                eventId: EventId::generate(),
                occurredAt: $now,
            );

            $this->quotes->insert($quote);
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'quote_id' => $quoteId->value,
                'client_id' => $clientId,
                'opportunity_id' => $opportunityId,
                'status' => $quote->status(),
                'total_cents' => $quote->totalCents(),
                'currency' => $quote->currency(),
                'version' => $quote->version(),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
