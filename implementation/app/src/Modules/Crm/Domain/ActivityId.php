<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Support\UuidGenerator;

final readonly class ActivityId
{
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('ActivityId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        return new self(UuidGenerator::generate());
    }
}
