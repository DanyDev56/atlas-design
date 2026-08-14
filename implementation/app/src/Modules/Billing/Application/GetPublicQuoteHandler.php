<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresPublicDocumentProofRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;

final class GetPublicQuoteHandler
{
    public function __construct(
        private readonly PostgresQuoteRepository $quotes,
        private readonly PostgresPublicDocumentProofRepository $proofs,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $workspaceId, string $quoteId, string $publicToken): array
    {
        $proof = $this->proofs->findValidByToken('quote', $publicToken);

        if (
            $proof === null
            || $proof['workspace_id'] !== $workspaceId
            || $proof['document_id'] !== $quoteId
        ) {
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

        return [
            'quote_id' => $quote->id()->value,
            'client_display_name' => $quote->clientSnapshot()['display_name'] ?? null,
            'status' => $quote->status(),
            'lines' => $quote->lines(),
            'total_cents' => $quote->totalCents(),
            'currency' => $quote->currency(),
            'version' => $quote->version(),
            'valid_until' => $quote->validUntil()?->format(DATE_ATOM),
        ];
    }
}
