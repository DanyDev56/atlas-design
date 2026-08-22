<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

use Atlas\Platform\Support\UuidGenerator;

final readonly class CreditNoteId
{
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('CreditNoteId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        return new self(UuidGenerator::generate());
    }
}
