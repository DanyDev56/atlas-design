<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\CreditNote;
use Atlas\Modules\Billing\Domain\CreditNoteEvent;
use Atlas\Modules\Billing\Domain\CreditNoteId;
use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresCreditNoteRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class CreditNoteCommandHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresInvoiceRepository $invoices,
        private readonly PostgresCreditNoteRepository $creditNotes,
        private readonly PostgresBillingIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
        private readonly BillingDocumentArtifactService $documents,
    ) {}

    /** @param list<array<string, mixed>> $lines */
    /** @return array<string, mixed> */
    public function create(
        string $actorUserId,
        string $workspaceId,
        string $invoiceId,
        array $lines,
        ?string $reason,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.credit-notes.create');
        $fingerprint = $this->fingerprint([$workspaceId, $invoiceId, $lines, $reason]);

        return $this->idempotent('billing.create_credit_note', $requestId, $fingerprint, function () use (
            $workspaceId, $invoiceId, $lines, $reason, $correlationId,
        ): array {
            $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId));
            if ($invoice === null) {
                throw new \DomainException('Invoice not found.');
            }
            if ($invoice->isHistoricalImport()) {
                throw new \DomainException('Historical imports are read-only.');
            }

            $now = $this->now();
            $creditNote = CreditNote::create(CreditNoteId::generate(), $invoice, $lines, $reason, $now);
            $this->creditNotes->insert($creditNote);
            $this->append('billing.credit_note_created', $creditNote, $now, $correlationId);

            return $this->response($creditNote);
        });
    }

    /** @param list<array<string, mixed>> $lines */
    /** @return array<string, mixed> */
    public function updateDraft(
        string $actorUserId,
        string $workspaceId,
        string $creditNoteId,
        array $lines,
        ?string $reason,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.credit-notes.update-draft');
        $fingerprint = $this->fingerprint([$workspaceId, $creditNoteId, $lines, $reason, $expectedRevision]);

        return $this->idempotent('billing.update_credit_note_draft', $requestId, $fingerprint, function () use (
            $workspaceId, $creditNoteId, $lines, $reason, $expectedRevision, $correlationId,
        ): array {
            $creditNote = $this->requireCreditNote($workspaceId, $creditNoteId);
            $now = $this->now();
            $creditNote->updateDraft($lines, $reason, $expectedRevision, $now);
            $this->creditNotes->update($creditNote);
            $this->append('billing.credit_note_draft_updated', $creditNote, $now, $correlationId);

            return $this->response($creditNote);
        });
    }

    /** @return array<string, mixed> */
    public function discard(
        string $actorUserId,
        string $workspaceId,
        string $creditNoteId,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.credit-notes.discard');
        $fingerprint = $this->fingerprint([$workspaceId, $creditNoteId, $expectedRevision]);

        return $this->idempotent('billing.discard_credit_note', $requestId, $fingerprint, function () use (
            $workspaceId, $creditNoteId, $expectedRevision, $correlationId,
        ): array {
            $creditNote = $this->requireCreditNote($workspaceId, $creditNoteId);
            $now = $this->now();
            $creditNote->discard($expectedRevision, $now);
            $this->creditNotes->update($creditNote);
            $this->append('billing.credit_note_discarded', $creditNote, $now, $correlationId);

            return $this->response($creditNote);
        });
    }

    /** @return array<string, mixed> */
    public function issue(
        string $actorUserId,
        string $workspaceId,
        string $creditNoteId,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.credit-notes.issue');
        $fingerprint = $this->fingerprint([$workspaceId, $creditNoteId, $expectedRevision]);

        return $this->idempotent('billing.issue_credit_note', $requestId, $fingerprint, function () use (
            $workspaceId, $creditNoteId, $expectedRevision, $correlationId,
        ): array {
            $creditNote = $this->requireCreditNote($workspaceId, $creditNoteId, true);
            $invoice = $this->invoices->findById($workspaceId, new InvoiceId($creditNote->invoiceId()), true);
            if ($invoice === null || $invoice->status() !== 'Issued') {
                throw new \DomainException('Invoice is not issued.');
            }

            $issuedTotal = $this->creditNotes->issuedTotalForInvoice($workspaceId, $creditNote->invoiceId());
            if ($issuedTotal + $creditNote->totalCents() > $invoice->totalCents()) {
                throw new \DomainException('Credit notes exceed invoice total.');
            }

            $now = $this->now();
            $creditNote->issue($this->creditNotes->nextNumber($workspaceId), $expectedRevision, $now);
            $this->creditNotes->update($creditNote);
            $this->documents->create(
                workspaceId: $workspaceId,
                documentType: 'credit_note',
                documentId: $creditNoteId,
                documentVersion: $creditNote->version(),
                documentNumber: $creditNote->number() ?? $creditNoteId,
                documentLines: $creditNote->lines(),
                totalCents: $creditNote->totalCents(),
                currency: $creditNote->currency(),
                clientSnapshot: $creditNote->clientSnapshot(),
            );
            $this->append('billing.credit_note_issued', $creditNote, $now, $correlationId);

            return $this->response($creditNote);
        });
    }

    /** @return array<string, mixed> */
    public function apply(
        string $actorUserId,
        string $workspaceId,
        string $creditNoteId,
        int $amountCents,
        ?string $remainderDisposition,
        int $expectedCreditNoteRevision,
        int $expectedInvoiceRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.credit-notes.apply');
        $fingerprint = $this->fingerprint([
            $workspaceId, $creditNoteId, $amountCents, $remainderDisposition,
            $expectedCreditNoteRevision, $expectedInvoiceRevision,
        ]);

        return $this->idempotent('billing.apply_credit_note', $requestId, $fingerprint, function () use (
            $workspaceId, $creditNoteId, $amountCents, $remainderDisposition,
            $expectedCreditNoteRevision, $expectedInvoiceRevision, $correlationId,
        ): array {
            $creditNote = $this->requireCreditNote($workspaceId, $creditNoteId, true);
            $invoice = $this->invoices->findById($workspaceId, new InvoiceId($creditNote->invoiceId()), true);
            if ($invoice === null) {
                throw new \DomainException('Invoice not found.');
            }
            if ($creditNote->currency() !== $invoice->currency()) {
                throw new \DomainException('Credit note currency conflict.');
            }

            $previousBalance = $invoice->balanceCents();
            $now = $this->now();
            $creditNote->apply($amountCents, $remainderDisposition, $expectedCreditNoteRevision, $now);
            $invoice->applyCreditNote($amountCents, $expectedInvoiceRevision, $now);

            $this->creditNotes->update($creditNote);
            $this->invoices->update($invoice);
            $this->append('billing.credit_note_applied_to_invoice', $creditNote, $now, $correlationId, [
                'amount_applied_cents' => $amountCents,
                'remaining_balance_cents' => $invoice->balanceCents(),
            ]);
            $this->appendRaw('billing.invoice_balance_changed', [
                'workspace_id' => $workspaceId,
                'invoice_id' => $invoice->id()->value,
                'previous_balance_cents' => $previousBalance,
                'new_balance_cents' => $invoice->balanceCents(),
                'aggregate_version' => $invoice->version(),
            ], $now, $correlationId);
            if ($invoice->balanceCents() === 0) {
                $this->appendRaw('billing.invoice_settled', [
                    'workspace_id' => $workspaceId,
                    'invoice_id' => $invoice->id()->value,
                    'aggregate_version' => $invoice->version(),
                ], $now, $correlationId);
            }

            return [
                ...$this->response($creditNote),
                'invoice_balance_cents' => $invoice->balanceCents(),
                'invoice_settlement_status' => $invoice->settlementStatus(),
                'invoice_version' => $invoice->version(),
            ];
        });
    }

    private function requireCreditNote(string $workspaceId, string $creditNoteId, bool $forUpdate = false): CreditNote
    {
        $creditNote = $this->creditNotes->findById($workspaceId, new CreditNoteId($creditNoteId), $forUpdate);
        if ($creditNote === null) {
            throw new \DomainException('Credit note not found.');
        }

        return $creditNote;
    }

    /** @param callable(): array<string, mixed> $action */
    /** @return array<string, mixed> */
    private function idempotent(string $scope, string $requestId, string $fingerprint, callable $action): array
    {
        $cached = $this->idempotency->find($scope, $requestId);
        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }
            return $cached['response_payload'];
        }

        return DB::transaction(function () use ($scope, $requestId, $fingerprint, $action): array {
            $response = $action();
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);
            return $response;
        });
    }

    /** @return array<string, mixed> */
    private function response(CreditNote $creditNote): array
    {
        return [
            'credit_note_id' => $creditNote->id()->value,
            'invoice_id' => $creditNote->invoiceId(),
            'status' => $creditNote->status(),
            'credit_note_number' => $creditNote->number(),
            'lines' => $creditNote->lines(),
            'total_cents' => $creditNote->totalCents(),
            'amount_applied_cents' => $creditNote->amountAppliedCents(),
            'unapplied_amount_cents' => $creditNote->unappliedAmountCents(),
            'remainder_disposition' => $creditNote->remainderDisposition(),
            'currency' => $creditNote->currency(),
            'reason' => $creditNote->reason(),
            'version' => $creditNote->version(),
        ];
    }

    /** @param array<string, mixed> $extra */
    private function append(
        string $type,
        CreditNote $creditNote,
        \DateTimeImmutable $now,
        ?string $correlationId,
        array $extra = [],
    ): void {
        $this->appendRaw($type, [
            'workspace_id' => $creditNote->workspaceId(),
            'credit_note_id' => $creditNote->id()->value,
            'invoice_id' => $creditNote->invoiceId(),
            'client_id' => $creditNote->clientId(),
            'total_cents' => $creditNote->totalCents(),
            'currency' => $creditNote->currency(),
            'aggregate_version' => $creditNote->version(),
            'issued_at' => $creditNote->issuedAt()?->format(DATE_ATOM),
            ...$extra,
        ], $now, $correlationId);
    }

    /** @param array<string, mixed> $payload */
    private function appendRaw(string $type, array $payload, \DateTimeImmutable $now, ?string $correlationId): void
    {
        $this->outbox->append(OutgoingMessage::fromDomainEvent(new CreditNoteEvent(
            type: $type,
            data: $payload,
            eventId: EventId::generate(),
            occurredAt: $now,
        ), correlationId: $correlationId));
    }

    /** @param array<mixed> $parts */
    private function fingerprint(array $parts): string
    {
        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
