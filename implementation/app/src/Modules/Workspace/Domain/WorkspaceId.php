<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Domain;

use Atlas\Platform\Support\UuidGenerator;

final readonly class WorkspaceId
{
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('WorkspaceId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        return new self(UuidGenerator::generate());
    }
}
