<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BUSINESS_HEALTH_PERMISSIONS = [
        'business-health.assessments.read',
    ];

    public function up(): void
    {
        $roles = DB::table('identity.roles')->where('name', 'owner')->get();

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);
            $merged = array_values(array_unique(array_merge($permissions, self::BUSINESS_HEALTH_PERMISSIONS)));

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
            $filtered = array_values(array_diff($permissions, self::BUSINESS_HEALTH_PERMISSIONS));

            DB::table('identity.roles')
                ->where('id', $role->id)
                ->update(['permissions' => json_encode($filtered, JSON_THROW_ON_ERROR)]);
        }
    }
};
