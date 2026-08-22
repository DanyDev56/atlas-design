<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

final class CreditNote
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_ISSUED = 'Issued';
    public const STATUS_APPLIED = 'Applied';
    public const STATUS_DISCARDED = 'Discarded';

    /** @param list<array<string, mixed>> $lines */
    private function __construct(
        private readonly CreditNoteId $id,
        private readonly string $workspaceId,
        private readonly string $invoiceId,
        private readonly string $clientId,
        private string $status,
        private ?string $number,
        private array $lines,
        private int $totalCents,
        private int $amountAppliedCents,
        private int $unappliedAmountCents,
        private ?string $remainderDisposition,
        private readonly string $currency,
        private readonly array $clientSnapshot,
        private ?string $reason,
        private int $version,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $issuedAt,
        private ?\DateTimeImmutable $appliedAt,
        private ?\DateTimeImmutable $discardedAt,
    ) {}

    /** @param list<array<string, mixed>> $lines */
    public static function create(
        CreditNoteId $id,
        Invoice $invoice,
        array $lines,
        ?string $reason,
        \DateTimeImmutable $now,
    ): self {
        self::validateLines($lines);

        if ($invoice->status() !== Invoice::STATUS_ISSUED) {
            throw new \DomainException('Invoice is not issued.');
        }

        return new self(
            id: $id,
            workspaceId: $invoice->workspaceId(),
            invoiceId: $invoice->id()->value,
            clientId: $invoice->clientId(),
            status: self::STATUS_DRAFT,
            number: null,
            lines: $lines,
            totalCents: LineCalculator::totalCents($lines),
            amountAppliedCents: 0,
            unappliedAmountCents: 0,
            remainderDisposition: null,
            currency: $invoice->currency(),
            clientSnapshot: $invoice->clientSnapshot(),
            reason: self::normalizeReason($reason),
            version: 1,
            createdAt: $now,
            updatedAt: $now,
            issuedAt: null,
            appliedAt: null,
            discardedAt: null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new CreditNoteId((string) $row['id']),
            workspaceId: (string) $row['workspace_id'],
            invoiceId: (string) $row['invoice_id'],
            clientId: (string) $row['client_id'],
            status: (string) $row['status'],
            number: $row['credit_note_number'] !== null ? (string) $row['credit_note_number'] : null,
            lines: json_decode((string) $row['lines'], true, 512, JSON_THROW_ON_ERROR),
            totalCents: (int) $row['total_cents'],
            amountAppliedCents: (int) $row['amount_applied_cents'],
            unappliedAmountCents: (int) $row['unapplied_amount_cents'],
            remainderDisposition: $row['remainder_disposition'] !== null ? (string) $row['remainder_disposition'] : null,
            currency: (string) $row['currency'],
            clientSnapshot: json_decode((string) $row['client_snapshot'], true, 512, JSON_THROW_ON_ERROR),
            reason: $row['reason'] !== null ? (string) $row['reason'] : null,
            version: (int) $row['version'],
            createdAt: new \DateTimeImmutable((string) $row['created_at']),
            updatedAt: new \DateTimeImmutable((string) $row['updated_at']),
            issuedAt: $row['issued_at'] !== null ? new \DateTimeImmutable((string) $row['issued_at']) : null,
            appliedAt: $row['applied_at'] !== null ? new \DateTimeImmutable((string) $row['applied_at']) : null,
            discardedAt: $row['discarded_at'] !== null ? new \DateTimeImmutable((string) $row['discarded_at']) : null,
        );
    }

    /** @param list<array<string, mixed>> $lines */
    public function updateDraft(array $lines, ?string $reason, int $expectedRevision, \DateTimeImmutable $now): void
    {
        $this->assertDraft($expectedRevision);
        self::validateLines($lines);
        $this->lines = $lines;
        $this->totalCents = LineCalculator::totalCents($lines);
        $this->reason = self::normalizeReason($reason);
        $this->version++;
        $this->updatedAt = $now;
    }

    public function discard(int $expectedRevision, \DateTimeImmutable $now): void
    {
        $this->assertDraft($expectedRevision);
        $this->status = self::STATUS_DISCARDED;
        $this->discardedAt = $now;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function issue(string $number, int $expectedRevision, \DateTimeImmutable $now): void
    {
        $this->assertDraft($expectedRevision);
        $this->status = self::STATUS_ISSUED;
        $this->number = $number;
        $this->issuedAt = $now;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function apply(
        int $amountCents,
        ?string $remainderDisposition,
        int $expectedRevision,
        \DateTimeImmutable $now,
    ): void {
        if ($this->status !== self::STATUS_ISSUED || $this->version !== $expectedRevision) {
            throw new \DomainException('Credit note version or state conflict.');
        }

        if ($amountCents <= 0 || $amountCents > $this->totalCents) {
            throw new \DomainException('Invalid credit note application amount.');
        }

        $unapplied = $this->totalCents - $amountCents;
        if ($unapplied > 0 && ! in_array($remainderDisposition, ['RefundDue', 'ClientCredit'], true)) {
            throw new \DomainException('Remainder disposition required.');
        }

        $this->status = self::STATUS_APPLIED;
        $this->amountAppliedCents = $amountCents;
        $this->unappliedAmountCents = $unapplied;
        $this->remainderDisposition = $unapplied > 0 ? $remainderDisposition : null;
        $this->appliedAt = $now;
        $this->version++;
        $this->updatedAt = $now;
    }

    private function assertDraft(int $expectedRevision): void
    {
        if ($this->status !== self::STATUS_DRAFT || $this->version !== $expectedRevision) {
            throw new \DomainException('Credit note version or state conflict.');
        }
    }

    /** @param list<array<string, mixed>> $lines */
    private static function validateLines(array $lines): void
    {
        if ($lines === [] || LineCalculator::totalCents($lines) <= 0) {
            throw new \DomainException('Credit note lines must have a positive total.');
        }

        foreach ($lines as $line) {
            if (
                trim((string) ($line['description'] ?? '')) === ''
                || (int) ($line['quantity'] ?? 0) <= 0
                || (int) ($line['unit_price_cents'] ?? 0) < 0
            ) {
                throw new \DomainException('Invalid credit note line.');
            }
        }
    }

    private static function normalizeReason(?string $reason): ?string
    {
        $normalized = $reason !== null ? trim($reason) : '';
        return $normalized !== '' ? $normalized : null;
    }

    public function id(): CreditNoteId { return $this->id; }
    public function workspaceId(): string { return $this->workspaceId; }
    public function invoiceId(): string { return $this->invoiceId; }
    public function clientId(): string { return $this->clientId; }
    public function status(): string { return $this->status; }
    public function number(): ?string { return $this->number; }
    /** @return list<array<string, mixed>> */
    public function lines(): array { return $this->lines; }
    public function totalCents(): int { return $this->totalCents; }
    public function amountAppliedCents(): int { return $this->amountAppliedCents; }
    public function unappliedAmountCents(): int { return $this->unappliedAmountCents; }
    public function remainderDisposition(): ?string { return $this->remainderDisposition; }
    public function currency(): string { return $this->currency; }
    /** @return array<string, mixed> */
    public function clientSnapshot(): array { return $this->clientSnapshot; }
    public function reason(): ?string { return $this->reason; }
    public function version(): int { return $this->version; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function issuedAt(): ?\DateTimeImmutable { return $this->issuedAt; }
    public function appliedAt(): ?\DateTimeImmutable { return $this->appliedAt; }
    public function discardedAt(): ?\DateTimeImmutable { return $this->discardedAt; }
}
