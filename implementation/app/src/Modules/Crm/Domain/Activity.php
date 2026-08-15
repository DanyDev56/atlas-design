<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

final class Activity
{
    public const KIND_NOTE = 'Note';

    public const KIND_CALL = 'Call';

    public const KIND_MEETING = 'Meeting';

    public const KIND_EMAIL = 'Email';

    public const STATUS_RECORDED = 'Recorded';

    public const STATUS_REMOVED = 'Removed';

    private function __construct(
        private readonly ActivityId $id,
        private readonly string $workspaceId,
        private readonly ClientId $clientId,
        private readonly ?string $contactId,
        private readonly ?string $opportunityId,
        private string $kind,
        private string $summary,
        private \DateTimeImmutable $occurredAt,
        private string $status,
        private int $version,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?string $removalReason,
        private ?string $removedBy,
        private ?\DateTimeImmutable $removedAt,
    ) {}

    public static function record(
        ActivityId $id,
        string $workspaceId,
        ClientId $clientId,
        ?string $contactId,
        ?string $opportunityId,
        string $kind,
        string $summary,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $now,
    ): self {
        $summary = self::validatedSummary($summary);
        self::assertContent($kind, $occurredAt, $now);

        return new self(
            id: $id,
            workspaceId: $workspaceId,
            clientId: $clientId,
            contactId: $contactId,
            opportunityId: $opportunityId,
            kind: $kind,
            summary: $summary,
            occurredAt: $occurredAt,
            status: self::STATUS_RECORDED,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
            removalReason: null,
            removedBy: null,
            removedAt: null,
        );
    }

    /** @return array{kind: string, summary: string, occurred_at: \DateTimeImmutable, version: int} */
    public function correct(
        string $kind,
        string $summary,
        \DateTimeImmutable $occurredAt,
        string $reason,
        \DateTimeImmutable $now,
    ): array {
        if ($this->status !== self::STATUS_RECORDED) {
            throw new \DomainException('Activity is not recorded.');
        }

        $summary = self::validatedSummary($summary);
        self::assertContent($kind, $occurredAt, $now);
        $reason = trim($reason);

        if (mb_strlen($reason) < 2 || mb_strlen($reason) > 500) {
            throw new \DomainException('Activity correction reason invalid.');
        }

        if ($kind === $this->kind && $summary === $this->summary && $occurredAt == $this->occurredAt) {
            throw new \DomainException('Activity unchanged.');
        }

        $previous = [
            'kind' => $this->kind,
            'summary' => $this->summary,
            'occurred_at' => $this->occurredAt,
            'version' => $this->version,
        ];
        $this->kind = $kind;
        $this->summary = $summary;
        $this->occurredAt = $occurredAt;
        $this->version++;
        $this->updatedAt = $now;

        return $previous;
    }

    public function remove(string $reason, string $actorUserId, \DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_RECORDED) {
            throw new \DomainException('Activity is not recorded.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 2 || mb_strlen($reason) > 500) {
            throw new \DomainException('Activity removal reason invalid.');
        }

        $this->status = self::STATUS_REMOVED;
        $this->removalReason = $reason;
        $this->removedBy = $actorUserId;
        $this->removedAt = $now;
        $this->version++;
        $this->updatedAt = $now;
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new ActivityId($row['id']),
            workspaceId: $row['workspace_id'],
            clientId: new ClientId($row['client_id']),
            contactId: $row['contact_id'],
            opportunityId: $row['opportunity_id'],
            kind: $row['kind'],
            summary: $row['summary'],
            occurredAt: new \DateTimeImmutable($row['occurred_at']),
            status: $row['status'],
            version: (int) $row['version'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
            removalReason: $row['removal_reason'] ?? null,
            removedBy: $row['removed_by'] ?? null,
            removedAt: isset($row['removed_at']) ? new \DateTimeImmutable($row['removed_at']) : null,
        );
    }

    /** @return list<string> */
    public static function kinds(): array
    {
        return [self::KIND_NOTE, self::KIND_CALL, self::KIND_MEETING, self::KIND_EMAIL];
    }

    private static function validatedSummary(string $summary): string
    {
        $summary = trim($summary);

        if (mb_strlen($summary) < 2 || mb_strlen($summary) > 2000) {
            throw new \DomainException('Activity summary invalid.');
        }

        return $summary;
    }

    private static function assertContent(
        string $kind,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $now,
    ): void {
        if (! in_array($kind, self::kinds(), true)) {
            throw new \DomainException('Activity kind invalid.');
        }

        if ($occurredAt > $now) {
            throw new \DomainException('Activity occurred at is in the future.');
        }
    }

    public function id(): ActivityId
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

    public function opportunityId(): ?string
    {
        return $this->opportunityId;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function status(): string
    {
        return $this->status;
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

    public function removalReason(): ?string
    {
        return $this->removalReason;
    }

    public function removedBy(): ?string
    {
        return $this->removedBy;
    }

    public function removedAt(): ?\DateTimeImmutable
    {
        return $this->removedAt;
    }
}
