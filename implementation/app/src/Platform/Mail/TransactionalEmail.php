<?php

declare(strict_types=1);

namespace Atlas\Platform\Mail;

final readonly class TransactionalEmail
{
    /** @param list<EmailAttachment> $attachments */
    public function __construct(
        public string $deliveryKey,
        public string $recipient,
        public string $subject,
        public string $text,
        public string $html,
        public array $attachments = [],
    ) {}
}
