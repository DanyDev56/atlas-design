<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\Quote;
use Atlas\Modules\Billing\Domain\QuoteDeliveryRequested;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Domain\QuoteSent;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresPublicDocumentProofRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Modules\Crm\Application\CrmQueryHandler;
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
        private readonly BillingDocumentArtifactService $documents,
        private readonly CrmQueryHandler $crmQueries,
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
            $actorUserId, $workspaceId, $quoteId, $expectedRevision,
            $requestId, $scope, $fingerprint,
        ): array {
            $quote = $this->quotes->findById($workspaceId, new QuoteId($quoteId));

            if ($quote === null) {
                throw new \DomainException('Quote not found.');
            }

            if ($quote->isHistoricalImport()) {
                throw new \DomainException('Historical imports are read-only.');
            }

            if (! in_array($quote->status(), [Quote::STATUS_DRAFT, Quote::STATUS_SENT], true)) {
                throw new \DomainException('Only draft or sent quotes can be delivered.');
            }

            if ($quote->version() !== $expectedRevision) {
                throw new \DomainException('Quote version conflict.');
            }

            $isResend = $quote->status() === Quote::STATUS_SENT;
            $recipient = $isResend
                ? $this->currentBillingEmail($actorUserId, $workspaceId, $quote->clientId())
                : ($this->billingEmail($quote->clientSnapshot())
                    ?? $this->currentBillingEmail($actorUserId, $workspaceId, $quote->clientId()));

            if ($recipient === null) {
                throw new \DomainException('A client billing email is required before sending this quote.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            if (! $isResend) {
                $quote->send($now);
                $this->quotes->update($quote);
                $this->documents->create(
                    workspaceId: $workspaceId,
                    documentType: 'quote',
                    documentId: $quoteId,
                    documentVersion: $quote->version(),
                    documentNumber: 'DEV-'.strtoupper(substr($quoteId, 0, 8)),
                    documentLines: $quote->lines(),
                    totalCents: $quote->totalCents(),
                    currency: $quote->currency(),
                    clientSnapshot: $quote->clientSnapshot(),
                );
            }

            $plainToken = PostgresPublicDocumentProofRepository::generatePlainToken();
            $expiresAt = $now->modify('+30 days');

            $proofId = $this->proofs->create(
                workspaceId: $workspaceId,
                documentType: 'quote',
                documentId: $quoteId,
                tokenHash: hash('sha256', $plainToken),
                capabilities: ['accept'],
                expiresAt: $expiresAt,
                deliverySecret: $plainToken,
                deliveryRecipient: $recipient,
            );

            if (! $isResend) {
                $event = new QuoteSent(
                    quoteId: new QuoteId($quoteId),
                    workspaceId: $workspaceId,
                    eventId: EventId::generate(),
                    occurredAt: $now,
                );
                $this->outbox->append(OutgoingMessage::fromDomainEvent($event));
            }

            $this->outbox->append(OutgoingMessage::fromDomainEvent(new QuoteDeliveryRequested(
                quoteId: new QuoteId($quoteId),
                workspaceId: $workspaceId,
                documentVersion: $quote->version(),
                deliverySecretHandle: $proofId,
                eventId: EventId::generate(),
                occurredAt: $now,
            )));

            $response = [
                'quote_id' => $quoteId,
                'status' => $quote->status(),
                'version' => $quote->version(),
                'public_accept_token' => $plainToken,
                'delivery_status' => 'Pending',
                'resent' => $isResend,
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }

    /** @param array<string, mixed> $snapshot */
    private function billingEmail(array $snapshot): ?string
    {
        return $this->validEmail($snapshot['billing_profile']['billing_email'] ?? null);
    }

    private function currentBillingEmail(string $actorUserId, string $workspaceId, string $clientId): ?string
    {
        $context = $this->crmQueries->getClientBillingContext($actorUserId, $workspaceId, $clientId);

        return $this->validEmail($context['current_billing_profile']['billing_email'] ?? null);
    }

    private function validEmail(mixed $email): ?string
    {
        $normalized = is_string($email) ? strtolower(trim($email)) : '';

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false ? $normalized : null;
    }
}
