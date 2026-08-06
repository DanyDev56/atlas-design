<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging;

interface OutboxWriter
{
    public function append(OutgoingMessage $message): void;
}
