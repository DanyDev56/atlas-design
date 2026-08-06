<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Domain;

use Atlas\Platform\Support\UuidGenerator;

final readonly class RoleId
{
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('RoleId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        return new self(UuidGenerator::generate());
    }
}
