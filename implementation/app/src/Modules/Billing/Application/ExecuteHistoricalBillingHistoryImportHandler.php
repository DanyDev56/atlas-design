<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\BillingHistoryImportCompleted;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresBillingHistoryImportPreviewRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresBillingHistoryImportRunRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class ExecuteHistoricalBillingHistoryImportHandler
{
    public function __construct(
        private readonly PostgresBillingHistoryImportRunRepository $runs,
        private readonly PostgresBillingHistoryImportPreviewRepository $previews,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(string $workspaceId, string $runId, ?string $correlationId = null): void
    {
        $run = $this->runs->findById($workspaceId, $runId);
        if ($run === null) {
            throw new \DomainException('Import run not found.');
        }
        if ($run['status'] === 'Completed') {
            return;
        }
        $preview = $this->previews->findById($workspaceId, (string) $run['preview_id']);
        if ($preview === null || $preview['package_hash'] !== $run['package_hash']) {
            throw new \DomainException('Import package unavailable.');
        }

        $checkpoint = (string) $run['checkpoint'];
        if ($checkpoint === 'Quotes') {
            $this->importQuotes($run, $preview);
            $this->runs->update($runId, ['checkpoint' => 'Invoices', 'processed_quotes' => count($preview['quotes'])]);
            $checkpoint = 'Invoices';
        }
        if ($checkpoint === 'Invoices') {
            $this->importInvoices($run, $preview);
            $this->runs->update($runId, ['checkpoint' => 'Payments', 'processed_invoices' => count($preview['invoices'])]);
            $checkpoint = 'Payments';
        }
        if ($checkpoint === 'Payments') {
            $this->importPayments($run, $preview);
            $this->runs->update($runId, ['checkpoint' => 'CreditNotes', 'processed_payments' => count($preview['payments'])]);
            $checkpoint = 'CreditNotes';
        }
        if ($checkpoint === 'CreditNotes') {
            $this->importCreditNotes($run, $preview);
            $this->runs->update($runId, ['checkpoint' => 'Validate', 'processed_credit_notes' => count($preview['credit_notes'] ?? [])]);
            $checkpoint = 'Validate';
        }
        if ($checkpoint === 'Validate') {
            $this->validateBalances($workspaceId, $runId, $preview);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            DB::transaction(function () use ($run, $preview, $runId, $workspaceId, $now, $correlationId): void {
                $this->runs->update($runId, [
                    'checkpoint' => 'Completed',
                    'status' => 'Completed',
                    'completed_at' => $now->format('Y-m-d H:i:sP'),
                ]);
                $this->outbox->append(OutgoingMessage::fromDomainEvent(new BillingHistoryImportCompleted(
                    $runId, $workspaceId, (string) $run['source_system'], (string) $run['package_hash'],
                    count($preview['quotes']), count($preview['invoices']), count($preview['payments']),
                    count($preview['credit_notes'] ?? []),
                    EventId::generate(), $now,
                ), correlationId: $correlationId));
            });
        }
    }

    /** @param array<string, mixed> $run @param array<string, mixed> $preview */
    private function importQuotes(array $run, array $preview): void
    {
        DB::transaction(function () use ($run, $preview): void {
            foreach ($preview['quotes'] as $record) {
                if ($this->identityExists('quotes', $run, $record)) {
                    continue;
                }
                $respondedAt = $record['responded_at'] !== '' ? $record['responded_at'] : null;
                DB::table('billing.quotes')->insert([
                    'id' => UuidGenerator::generate(),
                    'workspace_id' => $run['workspace_id'],
                    'client_id' => $record['client_id'],
                    'opportunity_id' => null,
                    'status' => $record['status'],
                    'lines' => json_encode([['kind' => 'HistoricalTotal', 'amount_cents' => (int) $record['gross_amount_cents']]], JSON_THROW_ON_ERROR),
                    'total_cents' => (int) $record['gross_amount_cents'],
                    'currency' => $record['currency'],
                    'client_snapshot' => json_encode(['historical_client_external_id' => $record['client_external_id']], JSON_THROW_ON_ERROR),
                    'opportunity_snapshot' => null,
                    'version' => 1,
                    'created_at' => $record['created_at'],
                    'updated_at' => $record['created_at'],
                    'sent_at' => $record['sent_at'],
                    'accepted_at' => $record['status'] === 'Accepted' ? $respondedAt : null,
                    'responded_at' => $respondedAt,
                    'valid_until' => $record['valid_until'],
                    ...$this->provenance($run, $record),
                    'original_number' => $record['original_number'],
                    'net_amount_cents' => (int) $record['net_amount_cents'],
                    'tax_amount_cents' => (int) $record['tax_amount_cents'],
                    'gross_amount_cents' => (int) $record['gross_amount_cents'],
                ]);
            }
        });
    }

    /** @param array<string, mixed> $run @param array<string, mixed> $preview */
    private function importInvoices(array $run, array $preview): void
    {
        DB::transaction(function () use ($run, $preview): void {
            foreach ($preview['invoices'] as $record) {
                if ($this->identityExists('invoices', $run, $record)) {
                    continue;
                }
                DB::table('billing.invoices')->insert([
                    'id' => UuidGenerator::generate(),
                    'workspace_id' => $run['workspace_id'],
                    'client_id' => $record['client_id'],
                    'quote_id' => null,
                    'status' => 'Issued',
                    'settlement_status' => 'Unpaid',
                    'invoice_number' => null,
                    'lines' => json_encode([['kind' => 'HistoricalTotal', 'amount_cents' => (int) $record['gross_amount_cents']]], JSON_THROW_ON_ERROR),
                    'total_cents' => (int) $record['gross_amount_cents'],
                    'balance_cents' => (int) $record['gross_amount_cents'],
                    'currency' => $record['currency'],
                    'client_snapshot' => json_encode(['historical_client_external_id' => $record['client_external_id']], JSON_THROW_ON_ERROR),
                    'version' => 1,
                    'created_at' => $record['issued_at'],
                    'updated_at' => $record['issued_at'],
                    'issued_at' => $record['issued_at'],
                    'sent_at' => null,
                    'due_date' => $record['due_date'],
                    'paid_at' => null,
                    ...$this->provenance($run, $record),
                    'original_number' => $record['original_number'],
                    'net_amount_cents' => (int) $record['net_amount_cents'],
                    'tax_amount_cents' => (int) $record['tax_amount_cents'],
                    'gross_amount_cents' => (int) $record['gross_amount_cents'],
                ]);
            }
        });
    }

    /** @param array<string, mixed> $run @param array<string, mixed> $preview */
    private function importPayments(array $run, array $preview): void
    {
        DB::transaction(function () use ($run, $preview): void {
            foreach ($preview['payments'] as $record) {
                if ($this->identityExists('payments', $run, $record)) {
                    continue;
                }
                $invoice = DB::table('billing.invoices')
                    ->where('workspace_id', $run['workspace_id'])
                    ->where('source_system', $run['source_system'])
                    ->where('external_id', $record['invoice_external_id'])
                    ->lockForUpdate()
                    ->first();
                if ($invoice === null) {
                    throw new \DomainException('Reference conflict.');
                }
                $balance = (int) $invoice->balance_cents - (int) $record['amount_applied_cents'];
                if ($balance < 0) {
                    throw new \DomainException('Calculation conflict.');
                }
                DB::table('billing.payments')->insert([
                    'id' => UuidGenerator::generate(),
                    'workspace_id' => $run['workspace_id'],
                    'invoice_id' => $invoice->id,
                    'amount_cents' => (int) $record['amount_applied_cents'],
                    'currency' => $record['currency'],
                    'reference' => null,
                    'recorded_at' => $record['received_at'],
                    'created_at' => $record['received_at'],
                    ...$this->provenance($run, $record),
                    'amount_received_cents' => (int) $record['amount_received_cents'],
                    'amount_applied_cents' => (int) $record['amount_applied_cents'],
                    'status' => 'Active',
                ]);
                DB::table('billing.invoices')->where('id', $invoice->id)->update([
                    'balance_cents' => $balance,
                    'settlement_status' => $balance === 0 ? 'Paid' : 'PartiallyPaid',
                    'paid_at' => $balance === 0 ? $record['received_at'] : null,
                    'updated_at' => $record['received_at'],
                ]);
            }
        });
    }

    /** @param array<string, mixed> $run @param array<string, mixed> $preview */
    private function importCreditNotes(array $run, array $preview): void
    {
        DB::transaction(function () use ($run, $preview): void {
            foreach ($preview['credit_notes'] ?? [] as $record) {
                if ($this->identityExists('credit_notes', $run, $record)) {
                    continue;
                }
                $invoice = DB::table('billing.invoices')
                    ->where('workspace_id', $run['workspace_id'])
                    ->where('source_system', $run['source_system'])
                    ->where('external_id', $record['invoice_external_id'])
                    ->lockForUpdate()
                    ->first();
                if ($invoice === null) {
                    throw new \DomainException('Reference conflict.');
                }
                $applied = (int) $record['amount_applied_cents'];
                $balance = (int) $invoice->balance_cents - $applied;
                if ($balance < 0) {
                    throw new \DomainException('Calculation conflict.');
                }
                $unapplied = (int) $record['gross_amount_cents'] - $applied;
                $issuedAt = $record['issued_at'];
                $appliedAt = $record['applied_at'];
                DB::table('billing.credit_notes')->insert([
                    'id' => UuidGenerator::generate(),
                    'workspace_id' => $run['workspace_id'],
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'status' => 'Applied',
                    'credit_note_number' => null,
                    'original_number' => $record['original_number'],
                    'net_amount_cents' => (int) $record['net_amount_cents'],
                    'tax_amount_cents' => (int) $record['tax_amount_cents'],
                    'gross_amount_cents' => (int) $record['gross_amount_cents'],
                    'lines' => json_encode([[
                        'kind' => 'HistoricalTotal',
                        'description' => 'Avoir historique',
                        'quantity' => 1,
                        'unit_price_cents' => (int) $record['gross_amount_cents'],
                    ]], JSON_THROW_ON_ERROR),
                    'total_cents' => (int) $record['gross_amount_cents'],
                    'amount_applied_cents' => $applied,
                    'unapplied_amount_cents' => $unapplied,
                    'remainder_disposition' => $unapplied > 0 ? $record['remainder_disposition'] : null,
                    'currency' => $record['currency'],
                    'client_snapshot' => $invoice->client_snapshot,
                    'reason' => $record['reason'] !== '' ? $record['reason'] : null,
                    'version' => 1,
                    'created_at' => $issuedAt,
                    'updated_at' => $appliedAt,
                    'issued_at' => $issuedAt,
                    'applied_at' => $appliedAt,
                    'discarded_at' => null,
                    ...$this->provenance($run, $record),
                ]);
                DB::table('billing.invoices')->where('id', $invoice->id)->update([
                    'balance_cents' => $balance,
                    'settlement_status' => $balance === 0 ? 'Paid' : (((int) $invoice->total_cents) === $balance ? 'Unpaid' : 'PartiallyPaid'),
                    'paid_at' => $balance === 0 ? $appliedAt : $invoice->paid_at,
                    'updated_at' => $appliedAt,
                ]);
            }
        });
    }

    /** @param array<string, mixed> $preview */
    private function validateBalances(string $workspaceId, string $runId, array $preview): void
    {
        foreach ($preview['invoices'] as $record) {
            $invoice = DB::table('billing.invoices')
                ->where('workspace_id', $workspaceId)->where('import_run_id', $runId)
                ->where('external_id', $record['external_id'])->first();
            if ($invoice === null) {
                throw new \DomainException('Import manifest incomplete.');
            }
            $appliedPayments = (int) DB::table('billing.payments')
                ->where('workspace_id', $workspaceId)->where('import_run_id', $runId)
                ->where('invoice_id', $invoice->id)->sum('amount_applied_cents');
            $appliedCredits = (int) DB::table('billing.credit_notes')
                ->where('workspace_id', $workspaceId)->where('import_run_id', $runId)
                ->where('invoice_id', $invoice->id)->sum('amount_applied_cents');
            if ((int) $invoice->balance_cents !== (int) $record['gross_amount_cents'] - $appliedPayments - $appliedCredits) {
                throw new \DomainException('Calculation conflict.');
            }
            $balance = (int) $invoice->balance_cents;
            $latestSettlementAt = $this->latestSettlementAt($workspaceId, $runId, (string) $invoice->id);
            DB::table('billing.invoices')->where('id', $invoice->id)->update([
                'settlement_status' => $balance === 0 ? 'Paid' : ($balance === (int) $record['gross_amount_cents'] ? 'Unpaid' : 'PartiallyPaid'),
                'paid_at' => $balance === 0 ? $latestSettlementAt : null,
                'updated_at' => $latestSettlementAt ?? $record['issued_at'],
            ]);
        }
        foreach (['quotes', 'invoices', 'payments', 'credit_notes'] as $table) {
            if (DB::table("billing.{$table}")->where('workspace_id', $workspaceId)->where('import_run_id', $runId)->count() !== count($preview[$table] ?? [])) {
                throw new \DomainException('Import manifest incomplete.');
            }
        }
    }

    private function latestSettlementAt(string $workspaceId, string $runId, string $invoiceId): ?string
    {
        $dates = [
            ...DB::table('billing.payments')
                ->where('workspace_id', $workspaceId)->where('import_run_id', $runId)
                ->where('invoice_id', $invoiceId)->pluck('recorded_at')->all(),
            ...DB::table('billing.credit_notes')
                ->where('workspace_id', $workspaceId)->where('import_run_id', $runId)
                ->where('invoice_id', $invoiceId)->pluck('applied_at')->all(),
        ];
        if ($dates === []) {
            return null;
        }

        usort($dates, static fn ($left, $right): int => new \DateTimeImmutable((string) $left) <=> new \DateTimeImmutable((string) $right));

        return (new \DateTimeImmutable((string) end($dates)))->format('Y-m-d H:i:sP');
    }

    /** @param array<string, mixed> $run @param array<string, mixed> $record */
    private function identityExists(string $table, array $run, array $record): bool
    {
        $existing = DB::table("billing.{$table}")
            ->where('workspace_id', $run['workspace_id'])->where('source_system', $run['source_system'])
            ->where('external_id', $record['external_id'])->first();
        if ($existing === null) {
            return false;
        }
        if ((string) $existing->canonical_record_hash !== (string) $record['canonical_record_hash']) {
            throw new \DomainException('Conflict.');
        }

        return true;
    }

    /** @param array<string, mixed> $run @param array<string, mixed> $record */
    private function provenance(array $run, array $record): array
    {
        return [
            'is_historical_import' => true,
            'source_system' => $run['source_system'],
            'external_id' => $record['external_id'],
            'import_run_id' => $run['id'],
            'canonical_record_hash' => $record['canonical_record_hash'],
            'source_exported_at' => $run['source_exported_at'],
        ];
    }
}
