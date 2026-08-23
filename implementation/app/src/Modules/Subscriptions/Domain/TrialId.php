<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

use Atlas\Platform\Support\UuidGenerator;

final readonly class TrialId
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('TrialId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        return new self(UuidGenerator::generate());
    }
}
