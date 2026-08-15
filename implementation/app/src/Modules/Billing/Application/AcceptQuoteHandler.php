<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\Quote;
use Atlas\Modules\Billing\Domain\QuoteAccepted;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresPublicDocumentProofRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class AcceptQuoteHandler
{
    public function __construct(
        private readonly PostgresQuoteRepository $quotes,
        private readonly PostgresPublicDocumentProofRepository $proofs,
        private readonly PostgresBillingIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(
        string $workspaceId,
        string $quoteId,
        string $publicToken,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $scope = 'billing.accept_quote';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $quoteId, $publicToken, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $quoteId, $publicToken, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $proof = $this->proofs->findValidByToken('quote', $publicToken);

            if ($proof === null) {
                throw new \DomainException('Invalid or expired proof.');
            }

            if ($proof['workspace_id'] !== $workspaceId || $proof['document_id'] !== $quoteId) {
                throw new \DomainException('Invalid or expired proof.');
            }

            $capabilities = json_decode($proof['capabilities'], true, 512, JSON_THROW_ON_ERROR);

            if (! in_array('accept', $capabilities, true)) {
                throw new \DomainException('Invalid or expired proof.');
            }

            $quote = $this->quotes->findById($workspaceId, new QuoteId($quoteId));

            if ($quote === null) {
                throw new \DomainException('Invalid or expired proof.');
            }

            if ($quote->status() === Quote::STATUS_ACCEPTED) {
                if ($quote->version() !== $expectedRevision) {
                    throw new \DomainException('Quote version conflict.');
                }

                return [
                    'quote_id' => $quoteId,
                    'status' => $quote->status(),
                    'version' => $quote->version(),
                ];
            }

            if ($quote->version() !== $expectedRevision) {
                throw new \DomainException('Quote version conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $quote->accept($now);
            $this->quotes->update($quote);
            $this->proofs->consume($proof['id']);

            $event = new QuoteAccepted(
                quoteId: new QuoteId($quoteId),
                workspaceId: $workspaceId,
                opportunityId: $quote->opportunityId(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'quote_id' => $quoteId,
                'status' => $quote->status(),
                'version' => $quote->version(),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
