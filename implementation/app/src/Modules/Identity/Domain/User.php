<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Domain;

final class User
{
    public const STATUS_PENDING = 'PendingVerification';

    public const STATUS_ACTIVE = 'Active';

    public const EMAIL_PENDING = 'Pending';

    public const EMAIL_VERIFIED = 'Verified';

    private function __construct(
        private readonly UserId $id,
        private string $email,
        private string $displayName,
        private string $passwordHash,
        private string $status,
        private string $emailVerificationStatus,
        private int $securityVersion,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function register(
        UserId $id,
        string $email,
        string $displayName,
        string $passwordHash,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            email: strtolower(trim($email)),
            displayName: $displayName,
            passwordHash: $passwordHash,
            status: self::STATUS_PENDING,
            emailVerificationStatus: self::EMAIL_PENDING,
            securityVersion: 1,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new UserId($row['id']),
            email: $row['email'],
            displayName: $row['display_name'],
            passwordHash: $row['password_hash'],
            status: $row['status'],
            emailVerificationStatus: $row['email_verification_status'],
            securityVersion: (int) $row['security_version'],
            version: (int) $row['version'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }

    public function verifyEmail(\DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \DomainException('User is not pending verification.');
        }

        $this->emailVerificationStatus = self::EMAIL_VERIFIED;
        $this->status = self::STATUS_ACTIVE;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function canAuthenticate(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function emailVerificationStatus(): string
    {
        return $this->emailVerificationStatus;
    }

    public function securityVersion(): int
    {
        return $this->securityVersion;
    }

    public function version(): int
    {
        return $this->version;
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
