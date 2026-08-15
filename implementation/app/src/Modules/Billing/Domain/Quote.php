<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

final class Quote
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_SENT = 'Sent';

    public const STATUS_ACCEPTED = 'Accepted';

    /** @param list<array<string, mixed>> $lines */
    private function __construct(
        private readonly QuoteId $id,
        private readonly string $workspaceId,
        private readonly string $clientId,
        private readonly ?string $opportunityId,
        private string $status,
        private array $lines,
        private int $totalCents,
        private string $currency,
        private array $clientSnapshot,
        private ?array $opportunitySnapshot,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $sentAt,
        private ?\DateTimeImmutable $acceptedAt,
        private ?\DateTimeImmutable $validUntil,
    ) {}

    /** @param list<array<string, mixed>> $lines */
    public static function createDraft(
        QuoteId $id,
        string $workspaceId,
        string $clientId,
        ?string $opportunityId,
        array $lines,
        int $totalCents,
        string $currency,
        array $clientSnapshot,
        ?array $opportunitySnapshot,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            workspaceId: $workspaceId,
            clientId: $clientId,
            opportunityId: $opportunityId,
            status: self::STATUS_DRAFT,
            lines: $lines,
            totalCents: $totalCents,
            currency: $currency,
            clientSnapshot: $clientSnapshot,
            opportunitySnapshot: $opportunitySnapshot,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
            sentAt: null,
            acceptedAt: null,
            validUntil: $now->modify('+30 days'),
        );
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new QuoteId($row['id']),
            workspaceId: $row['workspace_id'],
            clientId: $row['client_id'],
            opportunityId: $row['opportunity_id'],
            status: $row['status'],
            lines: json_decode($row['lines'], true, 512, JSON_THROW_ON_ERROR),
            totalCents: (int) $row['total_cents'],
            currency: $row['currency'],
            clientSnapshot: json_decode($row['client_snapshot'], true, 512, JSON_THROW_ON_ERROR),
            opportunitySnapshot: $row['opportunity_snapshot'] !== null
                ? json_decode($row['opportunity_snapshot'], true, 512, JSON_THROW_ON_ERROR)
                : null,
            version: (int) $row['version'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
            sentAt: isset($row['sent_at']) ? new \DateTimeImmutable($row['sent_at']) : null,
            acceptedAt: isset($row['accepted_at']) ? new \DateTimeImmutable($row['accepted_at']) : null,
            validUntil: isset($row['valid_until']) ? new \DateTimeImmutable($row['valid_until']) : null,
        );
    }

    /** @param list<array<string, mixed>> $lines */
    public function updateDraft(array $lines, int $totalCents, \DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \DomainException('Quote is not a draft.');
        }

        $this->lines = $lines;
        $this->totalCents = $totalCents;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function send(\DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \DomainException('Quote is not a draft.');
        }

        $this->status = self::STATUS_SENT;
        $this->sentAt = $now;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function accept(\DateTimeImmutable $now): void
    {
        if ($this->status === self::STATUS_ACCEPTED) {
            return;
        }

        if ($this->status !== self::STATUS_SENT) {
            throw new \DomainException('Quote is not sent.');
        }

        if ($this->validUntil !== null && $now > $this->validUntil) {
            throw new \DomainException('Quote has expired.');
        }

        $this->status = self::STATUS_ACCEPTED;
        $this->acceptedAt = $now;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function id(): QuoteId
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function opportunityId(): ?string
    {
        return $this->opportunityId;
    }

    public function status(): string
    {
        return $this->status;
    }

    /** @return list<array<string, mixed>> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function totalCents(): int
    {
        return $this->totalCents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /** @return array<string, mixed> */
    public function clientSnapshot(): array
    {
        return $this->clientSnapshot;
    }

    /** @return array<string, mixed>|null */
    public function opportunitySnapshot(): ?array
    {
        return $this->opportunitySnapshot;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function acceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function validUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }
}
