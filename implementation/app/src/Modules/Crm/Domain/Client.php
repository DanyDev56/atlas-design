<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

final class Client
{
    public const STATUS_ACTIVE = 'Active';

    public const STATUS_ARCHIVED = 'Archived';

    public const KIND_INDIVIDUAL = 'Individual';

    public const KIND_ORGANIZATION = 'Organization';

    /** @param array<string, mixed> $profile */
    /** @param array<string, mixed> $billingProfile */
    private function __construct(
        private readonly ClientId $id,
        private readonly string $workspaceId,
        private string $kind,
        private string $displayName,
        private array $profile,
        private array $billingProfile,
        private string $status,
        private ?string $primaryContactId,
        private int $profileVersion,
        private int $billingProfileVersion,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    /** @param array<string, mixed> $profile */
    public static function create(
        ClientId $id,
        string $workspaceId,
        string $kind,
        string $displayName,
        array $profile,
        array $billingProfile,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            workspaceId: $workspaceId,
            kind: $kind,
            displayName: $displayName,
            profile: $profile,
            billingProfile: $billingProfile,
            status: self::STATUS_ACTIVE,
            primaryContactId: null,
            profileVersion: 1,
            billingProfileVersion: empty($billingProfile) ? 0 : 1,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new ClientId($row['id']),
            workspaceId: $row['workspace_id'],
            kind: $row['kind'],
            displayName: $row['display_name'],
            profile: json_decode($row['profile'], true, 512, JSON_THROW_ON_ERROR),
            billingProfile: json_decode($row['billing_profile'], true, 512, JSON_THROW_ON_ERROR),
            status: $row['status'],
            primaryContactId: $row['primary_contact_id'],
            profileVersion: (int) $row['profile_version'],
            billingProfileVersion: (int) $row['billing_profile_version'],
            version: (int) $row['version'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }

    public function assignPrimaryContact(ContactId $contactId, \DateTimeImmutable $now): void
    {
        $this->changePrimaryContact($contactId, $now);
    }

    public function changePrimaryContact(?ContactId $contactId, \DateTimeImmutable $now): void
    {
        $this->primaryContactId = $contactId?->value;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function recordContactUpdated(\DateTimeImmutable $now): void
    {
        $this->version++;
        $this->updatedAt = $now;
    }

    public function recordContactArchived(\DateTimeImmutable $now): void
    {
        $this->version++;
        $this->updatedAt = $now;
    }

    public function recordContactReactivated(\DateTimeImmutable $now): void
    {
        $this->version++;
        $this->updatedAt = $now;
    }

    public function id(): ClientId
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    /** @return array<string, mixed> */
    public function profile(): array
    {
        return $this->profile;
    }

    /** @return array<string, mixed> */
    public function billingProfile(): array
    {
        return $this->billingProfile;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function primaryContactId(): ?string
    {
        return $this->primaryContactId;
    }

    public function profileVersion(): int
    {
        return $this->profileVersion;
    }

    public function billingProfileVersion(): int
    {
        return $this->billingProfileVersion;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
