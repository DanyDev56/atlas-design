<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

final class Invoice
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_ISSUED = 'Issued';

    public const SETTLEMENT_UNPAID = 'Unpaid';

    public const SETTLEMENT_PARTIALLY_PAID = 'PartiallyPaid';

    public const SETTLEMENT_PAID = 'Paid';

    public const KIND_FINAL = 'Final';

    public const KIND_DEPOSIT = 'Deposit';

    /** @param list<array<string, mixed>> $lines */
    private function __construct(
        private readonly InvoiceId $id,
        private readonly string $workspaceId,
        private readonly string $clientId,
        private readonly ?string $quoteId,
        private readonly string $kind,
        private string $status,
        private string $settlementStatus,
        private ?string $invoiceNumber,
        private array $lines,
        private int $totalCents,
        private int $balanceCents,
        private string $currency,
        private array $clientSnapshot,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $issuedAt,
        private ?\DateTimeImmutable $sentAt,
        private ?\DateTimeImmutable $dueDate,
        private ?\DateTimeImmutable $paidAt,
        private bool $historicalImport,
        private readonly ?string $originalNumber = null,
        private ?\DateTimeImmutable $lastRemindedAt = null,
        private int $reminderCount = 0,
    ) {}

    public static function createDraftFromQuote(
        InvoiceId $id,
        Quote $quote,
        \DateTimeImmutable $now,
        string $kind = self::KIND_FINAL,
        ?int $amountCents = null,
    ): self {
        if (! in_array($kind, [self::KIND_FINAL, self::KIND_DEPOSIT], true)) {
            throw new \DomainException('Invalid invoice kind.');
        }

        $total = $amountCents ?? $quote->totalCents();
        if ($total <= 0 || $total > $quote->totalCents()) {
            throw new \DomainException('Invoice amount exceeds quote total.');
        }

        $lines = $kind === self::KIND_DEPOSIT
            ? [['description' => 'Acompte', 'quantity' => 1, 'unit_price_cents' => $total]]
            : ($amountCents !== null && $amountCents < $quote->totalCents()
                ? [['description' => 'Solde du devis', 'quantity' => 1, 'unit_price_cents' => $total]]
                : $quote->lines());

        return new self(
            id: $id,
            workspaceId: $quote->workspaceId(),
            clientId: $quote->clientId(),
            quoteId: $quote->id()->value,
            kind: $kind,
            status: self::STATUS_DRAFT,
            settlementStatus: self::SETTLEMENT_UNPAID,
            invoiceNumber: null,
            lines: $lines,
            totalCents: $total,
            balanceCents: $total,
            currency: $quote->currency(),
            clientSnapshot: $quote->clientSnapshot(),
            version: 1,
            createdAt: $now,
            updatedAt: $now,
            issuedAt: null,
            sentAt: null,
            dueDate: null,
            paidAt: null,
            historicalImport: false,
        );
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new InvoiceId($row['id']),
            workspaceId: $row['workspace_id'],
            clientId: $row['client_id'],
            quoteId: $row['quote_id'],
            kind: (string) ($row['kind'] ?? self::KIND_FINAL),
            status: $row['status'],
            settlementStatus: $row['settlement_status'],
            invoiceNumber: $row['invoice_number'],
            lines: json_decode($row['lines'], true, 512, JSON_THROW_ON_ERROR),
            totalCents: (int) $row['total_cents'],
            balanceCents: (int) $row['balance_cents'],
            currency: $row['currency'],
            clientSnapshot: json_decode($row['client_snapshot'], true, 512, JSON_THROW_ON_ERROR),
            version: (int) $row['version'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
            issuedAt: isset($row['issued_at']) ? new \DateTimeImmutable($row['issued_at']) : null,
            sentAt: isset($row['sent_at']) ? new \DateTimeImmutable($row['sent_at']) : null,
            dueDate: isset($row['due_date']) ? new \DateTimeImmutable($row['due_date']) : null,
            paidAt: isset($row['paid_at']) ? new \DateTimeImmutable($row['paid_at']) : null,
            historicalImport: (bool) ($row['is_historical_import'] ?? false),
            originalNumber: $row['original_number'] ?? null,
            lastRemindedAt: isset($row['last_reminded_at']) ? new \DateTimeImmutable((string) $row['last_reminded_at']) : null,
            reminderCount: (int) ($row['reminder_count'] ?? 0),
        );
    }

    public function issue(string $invoiceNumber, \DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \DomainException('Invoice is not a draft.');
        }

        $this->status = self::STATUS_ISSUED;
        $this->invoiceNumber = $invoiceNumber;
        $this->issuedAt = $now;
        $this->dueDate = $now->modify('+30 days');
        $this->version++;
        $this->updatedAt = $now;
    }

    public function markSent(\DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_ISSUED) {
            throw new \DomainException('Invoice is not issued.');
        }

        $this->sentAt = $now;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function requestReminder(int $expectedRevision, \DateTimeImmutable $now): void
    {
        if ($this->historicalImport) {
            throw new \DomainException('Historical imports are read-only.');
        }

        if ($this->status !== self::STATUS_ISSUED) {
            throw new \DomainException('Invoice is not issued.');
        }

        if ($this->balanceCents <= 0) {
            throw new \DomainException('Invoice has no outstanding balance.');
        }

        if ($this->version !== $expectedRevision) {
            throw new \DomainException('Invoice version conflict.');
        }

        $this->lastRemindedAt = $now;
        $this->reminderCount++;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function applyPayment(int $amountCents, \DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_ISSUED) {
            throw new \DomainException('Invoice is not issued.');
        }

        if ($amountCents <= 0 || $amountCents > $this->balanceCents) {
            throw new \DomainException('Invalid payment amount.');
        }

        $this->balanceCents -= $amountCents;
        $this->settlementStatus = $this->balanceCents === 0
            ? self::SETTLEMENT_PAID
            : self::SETTLEMENT_PARTIALLY_PAID;
        if ($this->balanceCents === 0) {
            $this->paidAt = $now;
        }
        $this->version++;
        $this->updatedAt = $now;
    }

    public function applyCreditNote(int $amountCents, int $expectedRevision, \DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_ISSUED) {
            throw new \DomainException('Invoice is not issued.');
        }

        if ($this->version !== $expectedRevision) {
            throw new \DomainException('Invoice version conflict.');
        }

        if ($amountCents <= 0 || $amountCents > $this->balanceCents) {
            throw new \DomainException('Credit note exceeds invoice balance.');
        }

        $this->balanceCents -= $amountCents;
        $this->settlementStatus = $this->balanceCents === 0
            ? self::SETTLEMENT_PAID
            : self::SETTLEMENT_PARTIALLY_PAID;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function id(): InvoiceId
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function quoteId(): ?string
    {
        return $this->quoteId;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function settlementStatus(): string
    {
        return $this->settlementStatus;
    }

    public function balanceCents(): int
    {
        return $this->balanceCents;
    }

    public function totalCents(): int
    {
        return $this->totalCents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function invoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    /** @return list<array<string, mixed>> */
    public function lines(): array
    {
        return $this->lines;
    }

    /** @return array<string, mixed> */
    public function clientSnapshot(): array
    {
        return $this->clientSnapshot;
    }

    public function issuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function dueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function paidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function isHistoricalImport(): bool
    {
        return $this->historicalImport;
    }

    public function originalNumber(): ?string
    {
        return $this->originalNumber;
    }

    public function lastRemindedAt(): ?\DateTimeImmutable
    {
        return $this->lastRemindedAt;
    }

    public function reminderCount(): int
    {
        return $this->reminderCount;
    }
}
