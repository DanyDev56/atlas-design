<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

interface PlanCatalogRepository
{
    public function findVersion(string $code, int $version): ?Plan;
}
