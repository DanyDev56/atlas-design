<?php

declare(strict_types=1);

namespace Atlas\Platform\Mail;

final readonly class EmailAcceptance
{
    public function __construct(
        public string $provider,
        public string $providerMessageId,
    ) {}
}
