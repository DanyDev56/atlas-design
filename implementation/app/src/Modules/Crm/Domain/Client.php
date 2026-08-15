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
        private ?string $archiveReason,
        private ?\DateTimeImmutable $archivedAt,
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
            archiveReason: null,
            archivedAt: null,
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
            archiveReason: $row['archive_reason'] ?? null,
            archivedAt: isset($row['archived_at']) ? new \DateTimeImmutable($row['archived_at']) : null,
        );
    }

    public function archive(string $reason, \DateTimeImmutable $now): void
    {
        if (! $this->isActive()) {
            throw new \DomainException('Client is not active.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 2 || mb_strlen($reason) > 160) {
            throw new \DomainException('Archive reason invalid.');
        }

        $this->status = self::STATUS_ARCHIVED;
        $this->archiveReason = $reason;
        $this->archivedAt = $now;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function reactivate(\DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_ARCHIVED) {
            throw new \DomainException('Client is not archived.');
        }

        $displayName = trim($this->displayName);
        $profileDisplayName = $this->profile['display_name'] ?? null;

        if (
            ! in_array($this->kind, [self::KIND_INDIVIDUAL, self::KIND_ORGANIZATION], true)
            || mb_strlen($displayName) < 2
            || mb_strlen($displayName) > 160
            || ! is_string($profileDisplayName)
            || mb_strlen(trim($profileDisplayName)) < 2
            || mb_strlen(trim($profileDisplayName)) > 160
        ) {
            throw new \DomainException('Client profile invalid.');
        }

        $this->status = self::STATUS_ACTIVE;
        $this->version++;
        $this->updatedAt = $now;
    }

    /** @param array<string, mixed> $changes */
    public function updateProfile(array $changes, \DateTimeImmutable $now): void
    {
        if (! $this->isActive()) {
            throw new \DomainException('Client is not active.');
        }

        if ($changes === []) {
            throw new \DomainException('Client profile changes required.');
        }

        $allowed = [
            'display_name', 'legal_name', 'description', 'email', 'phone', 'website', 'postal_address',
        ];

        if (array_diff(array_keys($changes), $allowed) !== []) {
            throw new \DomainException('Client profile field invalid.');
        }

        $result = $this->profile;

        foreach ($changes as $key => $value) {
            if ($key === 'display_name') {
                if (! is_string($value)) {
                    throw new \DomainException('Client display name invalid.');
                }

                $result[$key] = trim($value);

                continue;
            }

            if ($key === 'postal_address') {
                if ($value === null || $value === []) {
                    unset($result[$key]);

                    continue;
                }

                if (! is_array($value)) {
                    throw new \DomainException('Client postal address invalid.');
                }

                $address = [];
                $addressLimits = [
                    'line1' => 160,
                    'line2' => 160,
                    'postal_code' => 32,
                    'city' => 100,
                    'country_code' => 2,
                ];

                if (array_diff(array_keys($value), array_keys($addressLimits)) !== []) {
                    throw new \DomainException('Client postal address invalid.');
                }

                foreach ($value as $addressKey => $addressValue) {
                    if ($addressValue === null || $addressValue === '') {
                        continue;
                    }

                    if (! is_string($addressValue)) {
                        throw new \DomainException('Client postal address invalid.');
                    }

                    $addressValue = trim($addressValue);

                    if ($addressValue === '' || mb_strlen($addressValue) > $addressLimits[$addressKey]) {
                        throw new \DomainException('Client postal address invalid.');
                    }

                    $address[$addressKey] = $addressKey === 'country_code'
                        ? strtoupper($addressValue)
                        : $addressValue;
                }

                if (isset($address['country_code']) && ! preg_match('/^[A-Z]{2}$/', $address['country_code'])) {
                    throw new \DomainException('Client postal address invalid.');
                }

                if ($address === []) {
                    unset($result[$key]);
                } else {
                    $result[$key] = $address;
                }

                continue;
            }

            if ($value === null || $value === '') {
                unset($result[$key]);

                continue;
            }

            if (! is_string($value)) {
                throw new \DomainException('Client profile invalid.');
            }

            $value = trim($value);

            if ($value === '') {
                unset($result[$key]);
            } else {
                $result[$key] = $value;
            }
        }

        $displayName = $result['display_name'] ?? null;

        if (! is_string($displayName) || mb_strlen($displayName) < 2 || mb_strlen($displayName) > 160) {
            throw new \DomainException('Client display name invalid.');
        }

        foreach (['legal_name' => 160, 'description' => 2000, 'phone' => 50] as $key => $maxLength) {
            if (isset($result[$key]) && (! is_string($result[$key]) || mb_strlen($result[$key]) > $maxLength)) {
                throw new \DomainException('Client profile invalid.');
            }
        }

        if (isset($result['email']) && (
            ! is_string($result['email'])
            || mb_strlen($result['email']) > 254
            || filter_var($result['email'], FILTER_VALIDATE_EMAIL) === false
        )) {
            throw new \DomainException('Client email invalid.');
        }

        if (isset($result['website']) && (
            ! is_string($result['website'])
            || mb_strlen($result['website']) > 2048
            || filter_var($result['website'], FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($result['website'], PHP_URL_SCHEME), ['http', 'https'], true)
        )) {
            throw new \DomainException('Client website invalid.');
        }

        if ($this->canonicalize($result) === $this->canonicalize($this->profile)) {
            throw new \DomainException('Client profile unchanged.');
        }

        $this->displayName = $displayName;
        $this->profile = $result;
        $this->profileVersion++;
        $this->version++;
        $this->updatedAt = $now;
    }

    /** @param array<string, mixed> $profile */
    public function updateBillingProfile(array $profile, \DateTimeImmutable $now): void
    {
        if (! $this->isActive()) {
            throw new \DomainException('Client is not active.');
        }

        $allowed = [
            'billing_name', 'billing_email', 'billing_address',
            'registration_identifiers', 'tax_identifiers',
        ];

        if (array_diff(array_keys($profile), $allowed) !== []) {
            throw new \DomainException('Client billing profile field invalid.');
        }

        $result = [];

        foreach (['billing_name' => 160, 'billing_email' => 254] as $key => $maxLength) {
            $value = $profile[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_string($value)) {
                throw new \DomainException('Client billing profile invalid.');
            }

            $value = trim($value);

            if ($value === '' || mb_strlen($value) > $maxLength) {
                throw new \DomainException('Client billing profile invalid.');
            }

            $result[$key] = $value;
        }

        if (isset($result['billing_email']) && filter_var($result['billing_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new \DomainException('Client billing email invalid.');
        }

        $billingAddress = $profile['billing_address'] ?? null;

        if ($billingAddress !== null && $billingAddress !== []) {
            if (! is_array($billingAddress)) {
                throw new \DomainException('Client billing address invalid.');
            }

            $address = [];
            $addressLimits = [
                'line1' => 160,
                'line2' => 160,
                'postal_code' => 32,
                'city' => 100,
                'country_code' => 2,
            ];

            if (array_diff(array_keys($billingAddress), array_keys($addressLimits)) !== []) {
                throw new \DomainException('Client billing address invalid.');
            }

            foreach ($billingAddress as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                if (! is_string($value)) {
                    throw new \DomainException('Client billing address invalid.');
                }

                $value = trim($value);

                if ($value === '' || mb_strlen($value) > $addressLimits[$key]) {
                    throw new \DomainException('Client billing address invalid.');
                }

                $address[$key] = $key === 'country_code' ? strtoupper($value) : $value;
            }

            if (isset($address['country_code']) && ! preg_match('/^[A-Z]{2}$/', $address['country_code'])) {
                throw new \DomainException('Client billing address invalid.');
            }

            if ($address !== []) {
                $result['billing_address'] = $address;
            }
        }

        foreach (['registration_identifiers', 'tax_identifiers'] as $collectionKey) {
            $identifiers = $profile[$collectionKey] ?? [];

            if (! is_array($identifiers) || ! array_is_list($identifiers)) {
                throw new \DomainException('Client billing identifiers invalid.');
            }

            $normalized = [];
            $declaredTypes = [];

            foreach ($identifiers as $identifier) {
                if (
                    ! is_array($identifier)
                    || array_diff(array_keys($identifier), ['type', 'value']) !== []
                    || ! isset($identifier['type'], $identifier['value'])
                    || ! is_string($identifier['type'])
                    || ! is_string($identifier['value'])
                ) {
                    throw new \DomainException('Client billing identifiers invalid.');
                }

                $type = strtoupper(trim($identifier['type']));
                $value = trim($identifier['value']);

                if (
                    ! preg_match('/^[A-Z][A-Z0-9_-]{0,31}$/', $type)
                    || $value === ''
                    || mb_strlen($value) > 160
                    || isset($declaredTypes[$type])
                ) {
                    throw new \DomainException('Client billing identifiers invalid.');
                }

                $declaredTypes[$type] = true;
                $normalized[] = ['type' => $type, 'value' => $value];
            }

            if ($normalized !== []) {
                $result[$collectionKey] = $normalized;
            }
        }

        if ($this->canonicalize($result) === $this->canonicalize($this->billingProfile)) {
            throw new \DomainException('Client billing profile unchanged.');
        }

        $this->billingProfile = $result;
        $this->billingProfileVersion++;
        $this->version++;
        $this->updatedAt = $now;
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

    public function archiveReason(): ?string
    {
        return $this->archiveReason;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @param array<string|int, mixed> $values */
    /** @return array<string|int, mixed> */
    private function canonicalize(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->canonicalize($value);
            }
        }

        ksort($values);

        return $values;
    }
}
