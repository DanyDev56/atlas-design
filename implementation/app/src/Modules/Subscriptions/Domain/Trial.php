<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

final class Trial
{
    public const string STATUS_ACTIVE = 'Active';

    private function __construct(
        private readonly TrialId $id,
        private readonly string $workspaceId,
        private readonly string $planId,
        private readonly string $status,
        private readonly \DateTimeImmutable $startedAt,
        private readonly \DateTimeImmutable $endsAt,
        private readonly int $version,
    ) {
        if (trim($workspaceId) === '' || trim($planId) === '' || $endsAt <= $startedAt || $version < 1) {
            throw new \InvalidArgumentException('Invalid trial.');
        }
    }

    public static function start(
        TrialId $id,
        string $workspaceId,
        string $planId,
        \DateTimeImmutable $startedAt,
        int $durationDays,
    ): self {
        if ($durationDays < 1) {
            throw new \InvalidArgumentException('Trial duration must be positive.');
        }

        return new self(
            id: $id,
            workspaceId: $workspaceId,
            planId: $planId,
            status: self::STATUS_ACTIVE,
            startedAt: $startedAt,
            endsAt: $startedAt->add(new \DateInterval('P'.$durationDays.'D')),
            version: 1,
        );
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new TrialId((string) $row['id']),
            workspaceId: (string) $row['workspace_id'],
            planId: (string) $row['plan_id'],
            status: (string) $row['status'],
            startedAt: new \DateTimeImmutable((string) $row['started_at']),
            endsAt: new \DateTimeImmutable((string) $row['ends_at']),
            version: (int) $row['version'],
        );
    }

    public function effectiveStatusAt(\DateTimeImmutable $now): string
    {
        return $this->status === self::STATUS_ACTIVE && $now >= $this->endsAt
            ? 'Expired'
            : $this->status;
    }

    public function remainingDaysAt(\DateTimeImmutable $now): int
    {
        if ($now >= $this->endsAt) {
            return 0;
        }

        return (int) ceil(($this->endsAt->getTimestamp() - $now->getTimestamp()) / 86400);
    }

    public function id(): TrialId
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function planId(): string
    {
        return $this->planId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function endsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function version(): int
    {
        return $this->version;
    }
}
