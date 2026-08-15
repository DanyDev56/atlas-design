<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION = 'crm.activities.correct';

    public function up(): void
    {
        foreach (DB::table('identity.roles')->where('name', 'owner')->get() as $role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);

            if (! in_array(self::PERMISSION, $permissions, true)) {
                $permissions[] = self::PERMISSION;
                DB::table('identity.roles')
                    ->where('id', $role->id)
                    ->update(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR)]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('identity.roles')->where('name', 'owner')->get() as $role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);
            $permissions = array_values(array_diff($permissions, [self::PERMISSION]));
            DB::table('identity.roles')
                ->where('id', $role->id)
                ->update(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR)]);
        }
    }
};
