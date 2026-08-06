<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging;

use Atlas\Platform\Support\UuidGenerator;

final readonly class EventId
{
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('EventId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        return new self(UuidGenerator::generate());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
