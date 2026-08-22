<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Infrastructure\Persistence;

use Atlas\Modules\Billing\Domain\CreditNote;
use Atlas\Modules\Billing\Domain\CreditNoteId;
use Illuminate\Support\Facades\DB;

final class PostgresCreditNoteRepository
{
    public function insert(CreditNote $creditNote): void
    {
        DB::table('billing.credit_notes')->insert($this->values($creditNote));
    }

    public function update(CreditNote $creditNote): void
    {
        DB::table('billing.credit_notes')
            ->where('id', $creditNote->id()->value)
            ->update($this->values($creditNote));
    }

    public function findById(string $workspaceId, CreditNoteId $id, bool $forUpdate = false): ?CreditNote
    {
        $query = DB::table('billing.credit_notes')
            ->where('workspace_id', $workspaceId)
            ->where('id', $id->value);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();
        return $row !== null ? CreditNote::reconstitute((array) $row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listForInvoice(string $workspaceId, string $invoiceId): array
    {
        return DB::table('billing.credit_notes')
            ->where('workspace_id', $workspaceId)
            ->where('invoice_id', $invoiceId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (object $row): array => $this->mapRow($row))
            ->all();
    }

    public function issuedTotalForInvoice(string $workspaceId, string $invoiceId): int
    {
        return (int) DB::table('billing.credit_notes')
            ->where('workspace_id', $workspaceId)
            ->where('invoice_id', $invoiceId)
            ->whereIn('status', [CreditNote::STATUS_ISSUED, CreditNote::STATUS_APPLIED])
            ->sum('total_cents');
    }

    public function nextNumber(string $workspaceId): string
    {
        $count = DB::table('billing.credit_notes')
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('credit_note_number')
            ->where('is_historical_import', false)
            ->count();

        return sprintf('CN-%06d', $count + 1);
    }

    /** @return array<string, mixed> */
    private function values(CreditNote $creditNote): array
    {
        return [
            'id' => $creditNote->id()->value,
            'workspace_id' => $creditNote->workspaceId(),
            'invoice_id' => $creditNote->invoiceId(),
            'client_id' => $creditNote->clientId(),
            'status' => $creditNote->status(),
            'credit_note_number' => $creditNote->number(),
            'lines' => json_encode($creditNote->lines(), JSON_THROW_ON_ERROR),
            'total_cents' => $creditNote->totalCents(),
            'amount_applied_cents' => $creditNote->amountAppliedCents(),
            'unapplied_amount_cents' => $creditNote->unappliedAmountCents(),
            'remainder_disposition' => $creditNote->remainderDisposition(),
            'currency' => $creditNote->currency(),
            'client_snapshot' => json_encode($creditNote->clientSnapshot(), JSON_THROW_ON_ERROR),
            'reason' => $creditNote->reason(),
            'version' => $creditNote->version(),
            'created_at' => $creditNote->createdAt()->format('Y-m-d H:i:sP'),
            'updated_at' => $creditNote->updatedAt()->format('Y-m-d H:i:sP'),
            'issued_at' => $creditNote->issuedAt()?->format('Y-m-d H:i:sP'),
            'applied_at' => $creditNote->appliedAt()?->format('Y-m-d H:i:sP'),
            'discarded_at' => $creditNote->discardedAt()?->format('Y-m-d H:i:sP'),
        ];
    }

    /** @return array<string, mixed> */
    private function mapRow(object $row): array
    {
        return [
            'credit_note_id' => $row->id,
            'invoice_id' => $row->invoice_id,
            'status' => $row->status,
            'credit_note_number' => $row->credit_note_number,
            'lines' => json_decode($row->lines, true, 512, JSON_THROW_ON_ERROR),
            'total_cents' => (int) $row->total_cents,
            'amount_applied_cents' => (int) $row->amount_applied_cents,
            'unapplied_amount_cents' => (int) $row->unapplied_amount_cents,
            'remainder_disposition' => $row->remainder_disposition,
            'currency' => $row->currency,
            'reason' => $row->reason,
            'version' => (int) $row->version,
            'issued_at' => $row->issued_at,
            'applied_at' => $row->applied_at,
        ];
    }
}
