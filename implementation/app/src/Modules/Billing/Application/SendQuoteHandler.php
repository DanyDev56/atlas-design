<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\Quote;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Domain\QuoteSent;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresPublicDocumentProofRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class SendQuoteHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresQuoteRepository $quotes,
        private readonly PostgresPublicDocumentProofRepository $proofs,
        private readonly PostgresBillingIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $quoteId,
        int $expectedRevision,
        string $requestId,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.quotes.send');

        $scope = 'billing.send_quote';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $quoteId, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $quoteId, $expectedRevision,
            $requestId, $scope, $fingerprint,
        ): array {
            $quote = $this->quotes->findById($workspaceId, new QuoteId($quoteId));

            if ($quote === null) {
                throw new \DomainException('Quote not found.');
            }

            if ($quote->isHistoricalImport()) {
                throw new \DomainException('Historical imports are read-only.');
            }

            if ($quote->status() === Quote::STATUS_SENT || $quote->status() === Quote::STATUS_ACCEPTED) {
                throw new \DomainException('Quote already sent.');
            }

            if ($quote->version() !== $expectedRevision) {
                throw new \DomainException('Quote version conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $quote->send($now);
            $this->quotes->update($quote);

            $plainToken = PostgresPublicDocumentProofRepository::generatePlainToken();
            $expiresAt = $now->modify('+30 days');

            $this->proofs->create(
                workspaceId: $workspaceId,
                documentType: 'quote',
                documentId: $quoteId,
                tokenHash: hash('sha256', $plainToken),
                capabilities: ['accept'],
                expiresAt: $expiresAt,
            );

            $event = new QuoteSent(
                quoteId: new QuoteId($quoteId),
                workspaceId: $workspaceId,
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event));

            $response = [
                'quote_id' => $quoteId,
                'status' => $quote->status(),
                'version' => $quote->version(),
                'public_accept_token' => $plainToken,
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
