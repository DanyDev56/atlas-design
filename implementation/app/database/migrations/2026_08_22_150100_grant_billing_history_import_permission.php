<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION = 'billing.history.import';

    public function up(): void
    {
        $this->updateRoles(true);
    }

    public function down(): void
    {
        $this->updateRoles(false);
    }

    private function updateRoles(bool $grant): void
    {
        foreach (DB::table('identity.roles')->where('name', 'owner')->get() as $role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);
            $permissions = $grant
                ? array_values(array_unique([...$permissions, self::PERMISSION]))
                : array_values(array_diff($permissions, [self::PERMISSION]));

            DB::table('identity.roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            ]);
        }
    }
};
