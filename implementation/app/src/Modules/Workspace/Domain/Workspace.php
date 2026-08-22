<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Domain;

final class Workspace
{
    public const STATUS_PROVISIONING = 'Provisioning';

    public const STATUS_ACTIVE = 'Active';

    public const ACCESS_RESTRICTED = 'Restricted';

    public const ACCESS_ACTIVE = 'Active';

    public const DEFAULT_LOCALE = 'fr-FR';

    public const DEFAULT_TIMEZONE = 'Europe/Paris';

    public const DEFAULT_CURRENCY = 'EUR';

    public const DEFAULT_COUNTRY = 'FR';

    /** @var list<string> */
    public const SUPPORTED_LOCALES = ['fr-FR', 'en-GB'];

    /** @var list<string> */
    public const SUPPORTED_TIMEZONES = ['Europe/Paris', 'UTC'];

    /** @var list<string> */
    public const SUPPORTED_CURRENCIES = ['EUR'];

    /** @var list<string> */
    public const SUPPORTED_COUNTRIES = ['FR', 'BE', 'CH'];

    private function __construct(
        private readonly WorkspaceId $id,
        private string $name,
        private ?string $tradingName,
        private ?string $activityDescription,
        private int $profileVersion,
        private ?string $legalName,
        private ?string $administrativeEmail,
        private int $billingIdentityVersion,
        private string $locale,
        private string $timezone,
        private string $defaultCurrency,
        private string $establishmentCountry,
        private int $preferencesVersion,
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
            tradingName: null,
            activityDescription: null,
            profileVersion: 1,
            legalName: null,
            administrativeEmail: null,
            billingIdentityVersion: 1,
            locale: self::DEFAULT_LOCALE,
            timezone: self::DEFAULT_TIMEZONE,
            defaultCurrency: self::DEFAULT_CURRENCY,
            establishmentCountry: self::DEFAULT_COUNTRY,
            preferencesVersion: 1,
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
            tradingName: self::nullableString($row['trading_name'] ?? null),
            activityDescription: self::nullableString($row['activity_description'] ?? null),
            profileVersion: (int) ($row['profile_version'] ?? 1),
            legalName: self::nullableString($row['legal_name'] ?? null),
            administrativeEmail: self::nullableString($row['administrative_email'] ?? null),
            billingIdentityVersion: (int) ($row['billing_identity_version'] ?? 1),
            locale: (string) ($row['locale'] ?? self::DEFAULT_LOCALE),
            timezone: (string) ($row['timezone'] ?? self::DEFAULT_TIMEZONE),
            defaultCurrency: (string) ($row['default_currency'] ?? self::DEFAULT_CURRENCY),
            establishmentCountry: (string) ($row['establishment_country'] ?? self::DEFAULT_COUNTRY),
            preferencesVersion: (int) ($row['preferences_version'] ?? 1),
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

    public function updateProfile(
        int $expectedRevision,
        string $displayName,
        ?string $tradingName,
        ?string $activityDescription,
        \DateTimeImmutable $now,
    ): void {
        $this->assertActive();
        $this->assertRevision($expectedRevision, $this->profileVersion);

        $normalizedName = self::normalizeRequiredName($displayName);
        $normalizedTrading = self::normalizeOptionalText($tradingName, 160);
        $normalizedDescription = self::normalizeOptionalText($activityDescription, 2000);

        if (
            $normalizedName === $this->name
            && $normalizedTrading === $this->tradingName
            && $normalizedDescription === $this->activityDescription
        ) {
            throw new \DomainException('No profile changes.');
        }

        $this->name = $normalizedName;
        $this->tradingName = $normalizedTrading;
        $this->activityDescription = $normalizedDescription;
        $this->profileVersion++;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function updateBillingIdentity(
        int $expectedRevision,
        ?string $legalName,
        ?string $administrativeEmail,
        \DateTimeImmutable $now,
    ): void {
        $this->assertActive();
        $this->assertRevision($expectedRevision, $this->billingIdentityVersion);

        $normalizedLegal = self::normalizeOptionalText($legalName, 160);
        $normalizedEmail = self::normalizeOptionalEmail($administrativeEmail);

        if ($normalizedLegal === $this->legalName && $normalizedEmail === $this->administrativeEmail) {
            throw new \DomainException('No billing identity changes.');
        }

        $this->legalName = $normalizedLegal;
        $this->administrativeEmail = $normalizedEmail;
        $this->billingIdentityVersion++;
        $this->version++;
        $this->updatedAt = $now;
    }

    public function changePreferences(
        int $expectedRevision,
        string $locale,
        string $timezone,
        string $defaultCurrency,
        string $establishmentCountry,
        \DateTimeImmutable $now,
    ): void {
        $this->assertActive();
        $this->assertRevision($expectedRevision, $this->preferencesVersion);

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)
            || ! in_array($timezone, self::SUPPORTED_TIMEZONES, true)
            || ! in_array($defaultCurrency, self::SUPPORTED_CURRENCIES, true)
            || ! in_array($establishmentCountry, self::SUPPORTED_COUNTRIES, true)
        ) {
            throw new \DomainException('Unsupported preference.');
        }

        if (
            $locale === $this->locale
            && $timezone === $this->timezone
            && $defaultCurrency === $this->defaultCurrency
            && $establishmentCountry === $this->establishmentCountry
        ) {
            throw new \DomainException('No preference changes.');
        }

        $this->locale = $locale;
        $this->timezone = $timezone;
        $this->defaultCurrency = $defaultCurrency;
        $this->establishmentCountry = $establishmentCountry;
        $this->preferencesVersion++;
        $this->version++;
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

    public function tradingName(): ?string
    {
        return $this->tradingName;
    }

    public function activityDescription(): ?string
    {
        return $this->activityDescription;
    }

    public function profileVersion(): int
    {
        return $this->profileVersion;
    }

    public function legalName(): ?string
    {
        return $this->legalName;
    }

    public function administrativeEmail(): ?string
    {
        return $this->administrativeEmail;
    }

    public function billingIdentityVersion(): int
    {
        return $this->billingIdentityVersion;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function defaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    public function establishmentCountry(): string
    {
        return $this->establishmentCountry;
    }

    public function preferencesVersion(): int
    {
        return $this->preferencesVersion;
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

    private function assertActive(): void
    {
        if ($this->status !== self::STATUS_ACTIVE || $this->accessState !== self::ACCESS_ACTIVE) {
            throw new \DomainException('Invalid state.');
        }
    }

    private function assertRevision(int $expected, int $current): void
    {
        if ($expected !== $current) {
            throw new \DomainException('Conflict.');
        }
    }

    private static function normalizeRequiredName(string $value): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if (mb_strlen($normalized) < 2 || mb_strlen($normalized) > 120) {
            throw new \DomainException('Invalid display name.');
        }

        return $normalized;
    }

    private static function normalizeOptionalText(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new \DomainException('Invalid input.');
        }

        return $normalized;
    }

    private static function normalizeOptionalEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new \DomainException('Invalid administrative email.');
        }

        return $normalized;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
