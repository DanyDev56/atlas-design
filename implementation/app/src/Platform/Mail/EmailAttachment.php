<?php

declare(strict_types=1);

namespace Atlas\Platform\Mail;

final readonly class EmailAttachment
{
    public function __construct(
        public string $name,
        public string $content,
        public string $mediaType,
    ) {}
}
