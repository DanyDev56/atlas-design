<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

final readonly class Entitlement
{
    /**
     * @param  list<string>  $fullCapabilities
     * @param  list<string>  $restrictedCapabilities
     * @param  array<string, int>  $limits
     */
    public function __construct(
        public string $workspaceId,
        public string $sourceType,
        public string $sourceId,
        public array $fullCapabilities,
        public array $restrictedCapabilities,
        public array $limits,
        public ?\DateTimeImmutable $validUntil,
        public \DateTimeImmutable $computedAt,
        public int $version,
    ) {
        if (trim($workspaceId) === '' || trim($sourceType) === '' || trim($sourceId) === '' || $version < 1) {
            throw new \InvalidArgumentException('Invalid entitlement.');
        }
    }

    public function accessLevelAt(\DateTimeImmutable $now): AccessLevel
    {
        return $this->validUntil !== null && $now >= $this->validUntil
            ? AccessLevel::Restricted
            : AccessLevel::Full;
    }

    public function allows(string $capability, \DateTimeImmutable $now): bool
    {
        $capabilities = $this->accessLevelAt($now) === AccessLevel::Full
            ? $this->fullCapabilities
            : $this->restrictedCapabilities;

        return in_array($capability, $capabilities, true);
    }
}
