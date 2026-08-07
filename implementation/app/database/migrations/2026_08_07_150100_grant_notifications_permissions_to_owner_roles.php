<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOTIFICATIONS_PERMISSIONS = [
        'notifications.inbox.read',
        'notifications.inbox.mark-read',
        'notifications.preferences.read',
        'notifications.preferences.change',
    ];

    public function up(): void
    {
        $roles = DB::table('identity.roles')->where('name', 'owner')->get();

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);
            $merged = array_values(array_unique(array_merge($permissions, self::NOTIFICATIONS_PERMISSIONS)));

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
            $filtered = array_values(array_diff($permissions, self::NOTIFICATIONS_PERMISSIONS));

            DB::table('identity.roles')
                ->where('id', $role->id)
                ->update(['permissions' => json_encode($filtered, JSON_THROW_ON_ERROR)]);
        }
    }
};
