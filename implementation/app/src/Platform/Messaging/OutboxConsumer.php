<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging;

interface OutboxConsumer
{
    public function name(): string;

    public function handle(OutgoingMessage $message): void;
}
