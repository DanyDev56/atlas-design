<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\CreditNoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresCreditNoteRepository;

final class GetCreditNoteAnalyticsFactHandler
{
    public function __construct(
        private readonly PostgresCreditNoteRepository $creditNotes,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $workspaceId, string $creditNoteId, int $aggregateVersion): array
    {
        $creditNote = $this->creditNotes->findById($workspaceId, new CreditNoteId($creditNoteId));
        if ($creditNote === null || $creditNote->version() < $aggregateVersion) {
            throw new \DomainException('Credit note not found.');
        }

        $fact = [
            'workspace_id' => $workspaceId,
            'credit_note_id' => $creditNoteId,
            'aggregate_version' => $aggregateVersion,
            'invoice_id' => $creditNote->invoiceId(),
            'client_id' => $creditNote->clientId(),
            'document_status' => 'Issued',
            'gross_amount_cents' => $creditNote->totalCents(),
            'currency_code' => $creditNote->currency(),
            'issued_at' => $creditNote->issuedAt()?->format(DATE_ATOM),
        ];
        $fact['fact_hash'] = hash('sha256', json_encode($fact, JSON_THROW_ON_ERROR));

        return $fact;
    }
}
