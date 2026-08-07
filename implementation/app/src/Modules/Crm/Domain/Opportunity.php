<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

final class Opportunity
{
    public const STATUS_OPEN = 'Open';
    public const STATUS_QUALIFIED = 'Qualified';
    public const STATUS_WON = 'Won';
    public const STATUS_LOST = 'Lost';

    private function __construct(
        private readonly OpportunityId $id,
        private readonly string $workspaceId,
        private readonly ClientId $clientId,
        private ?string $contactId,
        private string $title,
        private ?int $estimatedAmountCents,
        private string $currency,
        private string $status,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $qualifiedAt,
    ) {}

    public static function create(
        OpportunityId $id,
        string $workspaceId,
        ClientId $clientId,
        ?string $contactId,
        string $title,
        ?int $estimatedAmountCents,
        string $currency,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            workspaceId: $workspaceId,
            clientId: $clientId,
            contactId: $contactId,
            title: $title,
            estimatedAmountCents: $estimatedAmountCents,
            currency: $currency,
            status: self::STATUS_OPEN,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
            qualifiedAt: null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new OpportunityId($row['id']),
            workspaceId: $row['workspace_id'],
            clientId: new ClientId($row['client_id']),
            contactId: $row['contact_id'],
            title: $row['title'],
            estimatedAmountCents: $row['estimated_amount_cents'] !== null ? (int) $row['estimated_amount_cents'] : null,
            currency: $row['currency'],
            status: $row['status'],
            version: (int) $row['version'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
            qualifiedAt: isset($row['qualified_at']) ? new \DateTimeImmutable($row['qualified_at']) : null,
        );
    }

    public function qualify(\DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_OPEN) {
            throw new \DomainException('Opportunity is not open.');
        }

        $this->status = self::STATUS_QUALIFIED;
        $this->qualifiedAt = $now;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function win(\DateTimeImmutable $now): void
    {
        if ($this->status === self::STATUS_WON) {
            return;
        }

        if ($this->status !== self::STATUS_QUALIFIED) {
            throw new \DomainException('Opportunity is not qualified.');
        }

        $this->status = self::STATUS_WON;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function id(): OpportunityId
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function clientId(): ClientId
    {
        return $this->clientId;
    }

    public function contactId(): ?string
    {
        return $this->contactId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function estimatedAmountCents(): ?int
    {
        return $this->estimatedAmountCents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function qualifiedAt(): ?\DateTimeImmutable
    {
        return $this->qualifiedAt;
    }
}
