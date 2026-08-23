<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Contracts;

interface BetaCohortSource
{
    /** @return list<array<string, mixed>> */
    public function participants(\DateTimeImmutable $now, int $blockedAfterDays): array;

    /** @return array<string, mixed>|null */
    public function diagnostic(string $betaCode): ?array;
}
