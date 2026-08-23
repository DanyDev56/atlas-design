<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Persistence;

use Atlas\Modules\Subscriptions\Domain\BillingInterval;
use Atlas\Modules\Subscriptions\Domain\Plan;
use Atlas\Modules\Subscriptions\Domain\PlanCatalogRepository;
use Atlas\Modules\Subscriptions\Domain\PlanPrice;
use Illuminate\Support\Facades\DB;

final class PostgresPlanCatalogRepository implements PlanCatalogRepository
{
    public function findVersion(string $code, int $version): ?Plan
    {
        $row = DB::table('subscriptions.plans')
            ->where('code', $code)
            ->where('version', $version)
            ->first();

        if ($row === null) {
            return null;
        }

        $prices = DB::table('subscriptions.plan_prices')
            ->where('plan_id', $row->id)
            ->orderBy('amount_minor')
            ->get()
            ->map(fn (object $price): PlanPrice => new PlanPrice(
                id: (string) $price->id,
                interval: BillingInterval::from((string) $price->billing_interval),
                currency: (string) $price->currency,
                amountMinor: (int) $price->amount_minor,
                status: (string) $price->status,
            ))
            ->all();

        $entitlements = json_decode((string) $row->entitlements, true, 512, JSON_THROW_ON_ERROR);

        return new Plan(
            id: (string) $row->id,
            code: (string) $row->code,
            version: (int) $row->version,
            displayName: (string) $row->display_name,
            status: (string) $row->status,
            public: (bool) $row->is_public,
            capabilities: array_values($entitlements['capabilities'] ?? []),
            limits: $entitlements['limits'] ?? [],
            prices: $prices,
        );
    }
}
