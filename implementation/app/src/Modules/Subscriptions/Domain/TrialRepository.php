<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

interface TrialRepository
{
    public function findByWorkspaceId(string $workspaceId): ?Trial;

    public function save(Trial $trial): void;
}
