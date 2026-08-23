<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

final readonly class PortalSession
{
    public function __construct(
        public string $id,
        public string $url,
        public string $provider,
    ) {}
}
