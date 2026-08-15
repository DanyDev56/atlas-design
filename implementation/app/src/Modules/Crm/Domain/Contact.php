<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

final class Contact
{
    public const STATUS_ACTIVE = 'Active';

    /** @param array<string, mixed> $profile */
    private function __construct(
        private readonly ContactId $id,
        private readonly string $workspaceId,
        private readonly ClientId $clientId,
        private array $profile,
        private string $status,
        private int $version,
        private \DateTimeImmutable $createdAt,
    ) {}

    /** @param array<string, mixed> $profile */
    public static function create(
        ContactId $id,
        string $workspaceId,
        ClientId $clientId,
        array $profile,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            workspaceId: $workspaceId,
            clientId: $clientId,
            profile: $profile,
            status: self::STATUS_ACTIVE,
            version: 1,
            createdAt: $now,
        );
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new ContactId($row['id']),
            workspaceId: $row['workspace_id'],
            clientId: new ClientId($row['client_id']),
            profile: json_decode($row['profile'], true, 512, JSON_THROW_ON_ERROR),
            status: $row['status'],
            version: (int) $row['version'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }

    /** @param array<string, mixed> $changes */
    public function updateProfile(array $changes): void
    {
        if ($changes === []) {
            throw new \DomainException('Contact changes required.');
        }

        $result = $this->profile;

        foreach ($changes as $key => $value) {
            if ($value === null || $value === '') {
                unset($result[$key]);
            } else {
                $result[$key] = $value;
            }
        }

        if (! isset($result['display_name']) || ! is_string($result['display_name'])) {
            throw new \DomainException('Contact display name required.');
        }

        $displayName = trim($result['display_name']);

        if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 160) {
            throw new \DomainException('Contact display name invalid.');
        }

        $result['display_name'] = $displayName;

        if ($result === $this->profile) {
            throw new \DomainException('Contact profile unchanged.');
        }

        $this->profile = $result;
        $this->version++;
    }

    public function id(): ContactId
    {
        return $this->id;
    }

    public function clientId(): ClientId
    {
        return $this->clientId;
    }

    /** @return array<string, mixed> */
    public function profile(): array
    {
        return $this->profile;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }
}
