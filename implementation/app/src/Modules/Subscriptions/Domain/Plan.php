<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

final readonly class Plan
{
    /**
     * @param  list<string>  $capabilities
     * @param  array<string, int>  $limits
     * @param  list<PlanPrice>  $prices
     */
    public function __construct(
        public string $id,
        public string $code,
        public int $version,
        public string $displayName,
        public string $status,
        public bool $public,
        public array $capabilities,
        public array $limits,
        public array $prices,
    ) {
        if (
            trim($id) === ''
            || preg_match('/^[a-z][a-z0-9_]*$/', $code) !== 1
            || $version < 1
            || trim($displayName) === ''
        ) {
            throw new \InvalidArgumentException('Invalid plan.');
        }
    }

    public function priceFor(BillingInterval $interval): ?PlanPrice
    {
        foreach ($this->prices as $price) {
            if ($price->interval === $interval) {
                return $price;
            }
        }

        return null;
    }
}
