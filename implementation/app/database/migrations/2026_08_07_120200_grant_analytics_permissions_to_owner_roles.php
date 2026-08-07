<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ANALYTICS_PERMISSIONS = [
        'analytics.metrics.read',
        'analytics.facts.ingest',
        'analytics.projections.rebuild',
        'analytics.snapshots.publish',
        'analytics.snapshots.consume',
        'crm.analytics-facts.read',
        'billing.analytics-facts.read',
    ];

    public function up(): void
    {
        $roles = DB::table('identity.roles')->where('name', 'owner')->get();

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);
            $merged = array_values(array_unique(array_merge($permissions, self::ANALYTICS_PERMISSIONS)));

            DB::table('identity.roles')
                ->where('id', $role->id)
                ->update(['permissions' => json_encode($merged, JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
        $roles = DB::table('identity.roles')->where('name', 'owner')->get();

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);
            $filtered = array_values(array_diff($permissions, self::ANALYTICS_PERMISSIONS));

            DB::table('identity.roles')
                ->where('id', $role->id)
                ->update(['permissions' => json_encode($filtered, JSON_THROW_ON_ERROR)]);
        }
    }
};
