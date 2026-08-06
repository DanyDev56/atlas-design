<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Domain;

final class Workspace
{
    public const STATUS_PROVISIONING = 'Provisioning';
    public const STATUS_ACTIVE = 'Active';
    public const ACCESS_RESTRICTED = 'Restricted';
    public const ACCESS_ACTIVE = 'Active';

    private function __construct(
        private readonly WorkspaceId $id,
        private string $name,
        private string $status,
        private string $accessState,
        private int $version,
        private int $governanceVersion,
        private ?string $requestedByUserId,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $activatedAt,
    ) {}

    public static function create(
        WorkspaceId $id,
        string $name,
        string $requestedByUserId,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            name: $name,
            status: self::STATUS_PROVISIONING,
            accessState: self::ACCESS_RESTRICTED,
            version: 1,
            governanceVersion: 1,
            requestedByUserId: $requestedByUserId,
            createdAt: $now,
            updatedAt: $now,
            activatedAt: null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new WorkspaceId($row['id']),
            name: $row['name'],
            status: $row['status'],
            accessState: $row['access_state'],
            version: (int) $row['version'],
            governanceVersion: (int) $row['governance_version'],
            requestedByUserId: $row['requested_by_user_id'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
            activatedAt: isset($row['activated_at']) ? new \DateTimeImmutable($row['activated_at']) : null,
        );
    }

    public function activate(\DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_PROVISIONING) {
            throw new \DomainException('Workspace is not in Provisioning state.');
        }

        $this->status = self::STATUS_ACTIVE;
        $this->accessState = self::ACCESS_ACTIVE;
        $this->governanceVersion++;
        $this->version++;
        $this->activatedAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): WorkspaceId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function accessState(): string
    {
        return $this->accessState;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function governanceVersion(): int
    {
        return $this->governanceVersion;
    }

    public function requestedByUserId(): ?string
    {
        return $this->requestedByUserId;
    }

    public function activatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
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
