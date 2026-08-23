<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
        public string $provider,
    ) {}
}
