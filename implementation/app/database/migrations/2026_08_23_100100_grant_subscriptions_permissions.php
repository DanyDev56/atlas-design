<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array PERMISSIONS = [
        'subscriptions.read',
        'subscriptions.manage',
    ];

    public function up(): void
    {
        $this->updateOwnerRoles(true);
    }

    public function down(): void
    {
        $this->updateOwnerRoles(false);
    }

    private function updateOwnerRoles(bool $grant): void
    {
        foreach (DB::table('identity.roles')->where('name', 'owner')->get() as $role) {
            $permissions = json_decode((string) $role->permissions, true, 512, JSON_THROW_ON_ERROR);
            $permissions = $grant
                ? array_values(array_unique([...$permissions, ...self::PERMISSIONS]))
                : array_values(array_diff($permissions, self::PERMISSIONS));

            DB::table('identity.roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            ]);
        }
    }
};
