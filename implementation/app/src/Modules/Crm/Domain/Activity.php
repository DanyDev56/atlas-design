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

    private function __construct(
        private readonly ActivityId $id,
        private readonly string $workspaceId,
        private readonly ClientId $clientId,
        private readonly ?string $contactId,
        private readonly ?string $opportunityId,
        private readonly string $kind,
        private readonly string $summary,
        private readonly \DateTimeImmutable $occurredAt,
        private readonly string $status,
        private readonly int $version,
        private readonly \DateTimeImmutable $createdAt,
        private readonly \DateTimeImmutable $updatedAt,
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
        $summary = trim($summary);

        if (! in_array($kind, self::kinds(), true)) {
            throw new \DomainException('Activity kind invalid.');
        }

        if (mb_strlen($summary) < 2 || mb_strlen($summary) > 2000) {
            throw new \DomainException('Activity summary invalid.');
        }

        if ($occurredAt > $now) {
            throw new \DomainException('Activity occurred at is in the future.');
        }

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
        );
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
        );
    }

    /** @return list<string> */
    public static function kinds(): array
    {
        return [self::KIND_NOTE, self::KIND_CALL, self::KIND_MEETING, self::KIND_EMAIL];
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
}
