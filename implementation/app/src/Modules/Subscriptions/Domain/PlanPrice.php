<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

final readonly class PlanPrice
{
    public function __construct(
        public string $id,
        public BillingInterval $interval,
        public string $currency,
        public int $amountMinor,
        public string $status,
    ) {
        if (trim($id) === '' || $amountMinor < 0 || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException('Invalid plan price.');
        }
    }
}
