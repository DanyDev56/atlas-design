<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Domain;

interface WorkspaceRepository
{
    public function save(Workspace $workspace): void;

    public function findById(WorkspaceId $id): ?Workspace;
}
