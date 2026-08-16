<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

final class Opportunity
{
    public const STATUS_OPEN = 'Open';

    public const STATUS_QUALIFIED = 'Qualified';

    public const STATUS_WON = 'Won';

    public const STATUS_LOST = 'Lost';

    public const WIN_SOURCE_MANUAL = 'Manual';

    public const WIN_SOURCE_ACCEPTED_QUOTE = 'AcceptedQuote';

    public const LOSS_REASON_BUDGET = 'Budget';

    public const LOSS_REASON_TIMING = 'Timing';

    public const LOSS_REASON_COMPETITOR = 'Competitor';

    public const LOSS_REASON_NO_DECISION = 'NoDecision';

    public const LOSS_REASON_OTHER = 'Other';

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
        private ?string $lossReasonCode,
        private ?string $lossNote,
        private ?\DateTimeImmutable $lostAt,
        private ?string $winSource,
        private ?string $wonQuoteId,
        private ?string $wonBy,
        private ?\DateTimeImmutable $wonAt,
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
            lossReasonCode: null,
            lossNote: null,
            lostAt: null,
            winSource: null,
            wonQuoteId: null,
            wonBy: null,
            wonAt: null,
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
            lossReasonCode: $row['loss_reason_code'] ?? null,
            lossNote: $row['loss_note'] ?? null,
            lostAt: isset($row['lost_at']) ? new \DateTimeImmutable($row['lost_at']) : null,
            winSource: $row['win_source'] ?? null,
            wonQuoteId: $row['won_quote_id'] ?? null,
            wonBy: $row['won_by'] ?? null,
            wonAt: isset($row['won_at']) ? new \DateTimeImmutable($row['won_at']) : null,
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

    /** @param array<string, mixed> $changes */
    public function updateDetails(array $changes, \DateTimeImmutable $now): void
    {
        if (! $this->isNonTerminal()) {
            throw new \DomainException('Opportunity is terminal.');
        }

        if ($changes === [] || array_diff(array_keys($changes), [
            'contact_id', 'title', 'estimated_amount_cents', 'currency',
        ]) !== []) {
            throw new \DomainException('Opportunity changes invalid.');
        }

        $contactId = array_key_exists('contact_id', $changes) ? $changes['contact_id'] : $this->contactId;
        $title = array_key_exists('title', $changes) ? $changes['title'] : $this->title;
        $estimatedAmountCents = array_key_exists('estimated_amount_cents', $changes)
            ? $changes['estimated_amount_cents']
            : $this->estimatedAmountCents;
        $currency = array_key_exists('currency', $changes) ? $changes['currency'] : $this->currency;

        if ($contactId !== null && ! is_string($contactId)) {
            throw new \DomainException('Opportunity contact invalid.');
        }

        if (! is_string($title)) {
            throw new \DomainException('Opportunity title invalid.');
        }

        $title = trim($title);

        if (mb_strlen($title) < 2 || mb_strlen($title) > 200) {
            throw new \DomainException('Opportunity title invalid.');
        }

        if ($estimatedAmountCents !== null
            && (! is_int($estimatedAmountCents) || $estimatedAmountCents < 0)) {
            throw new \DomainException('Opportunity amount invalid.');
        }

        if (! is_string($currency) || preg_match('/^[A-Z]{3}$/', strtoupper($currency)) !== 1) {
            throw new \DomainException('Opportunity currency invalid.');
        }

        $currency = strtoupper($currency);

        if ($contactId === $this->contactId
            && $title === $this->title
            && $estimatedAmountCents === $this->estimatedAmountCents
            && $currency === $this->currency) {
            throw new \DomainException('Opportunity unchanged.');
        }

        $this->contactId = $contactId;
        $this->title = $title;
        $this->estimatedAmountCents = $estimatedAmountCents;
        $this->currency = $currency;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function win(
        string $source,
        ?string $quoteId,
        ?string $actorUserId,
        \DateTimeImmutable $now,
    ): void {
        if ($this->status !== self::STATUS_QUALIFIED) {
            throw new \DomainException('Opportunity is not qualified.');
        }

        if (! in_array($source, [self::WIN_SOURCE_MANUAL, self::WIN_SOURCE_ACCEPTED_QUOTE], true)) {
            throw new \DomainException('Opportunity win source invalid.');
        }

        if (($source === self::WIN_SOURCE_MANUAL && ($quoteId !== null || $actorUserId === null))
            || ($source === self::WIN_SOURCE_ACCEPTED_QUOTE && ($quoteId === null || $actorUserId !== null))) {
            throw new \DomainException('Opportunity win result invalid.');
        }

        $this->status = self::STATUS_WON;
        $this->winSource = $source;
        $this->wonQuoteId = $quoteId;
        $this->wonBy = $actorUserId;
        $this->wonAt = $now;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function lose(string $reasonCode, ?string $note, \DateTimeImmutable $now): void
    {
        if (! $this->isNonTerminal()) {
            throw new \DomainException('Opportunity is terminal.');
        }

        if (! in_array($reasonCode, self::lossReasonCodes(), true)) {
            throw new \DomainException('Opportunity loss reason invalid.');
        }

        $note = $note !== null ? trim($note) : null;
        $note = $note !== '' ? $note : null;

        if ($note !== null && mb_strlen($note) > 500) {
            throw new \DomainException('Opportunity loss note invalid.');
        }

        $this->status = self::STATUS_LOST;
        $this->lossReasonCode = $reasonCode;
        $this->lossNote = $note;
        $this->lostAt = $now;
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

    public function isNonTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_QUALIFIED], true);
    }

    public function qualifiedAt(): ?\DateTimeImmutable
    {
        return $this->qualifiedAt;
    }

    /** @return list<string> */
    public static function lossReasonCodes(): array
    {
        return [
            self::LOSS_REASON_BUDGET,
            self::LOSS_REASON_TIMING,
            self::LOSS_REASON_COMPETITOR,
            self::LOSS_REASON_NO_DECISION,
            self::LOSS_REASON_OTHER,
        ];
    }

    public function lossReasonCode(): ?string
    {
        return $this->lossReasonCode;
    }

    public function lossNote(): ?string
    {
        return $this->lossNote;
    }

    public function lostAt(): ?\DateTimeImmutable
    {
        return $this->lostAt;
    }

    public function winSource(): ?string
    {
        return $this->winSource;
    }

    public function wonQuoteId(): ?string
    {
        return $this->wonQuoteId;
    }

    public function wonBy(): ?string
    {
        return $this->wonBy;
    }

    public function wonAt(): ?\DateTimeImmutable
    {
        return $this->wonAt;
    }
}
