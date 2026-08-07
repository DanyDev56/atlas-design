<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CRM_PERMISSIONS = [
        'crm.clients.read',
        'crm.clients.create',
        'crm.clients.update-profile',
        'crm.clients.update-billing-profile',
        'crm.clients.archive',
        'crm.clients.reactivate',
        'crm.contacts.read',
        'crm.contacts.create',
        'crm.contacts.update',
        'crm.contacts.change-primary',
        'crm.contacts.archive',
        'crm.contacts.reactivate',
        'crm.opportunities.read',
        'crm.opportunities.create',
        'crm.opportunities.update',
        'crm.opportunities.qualify',
        'crm.opportunities.win',
        'crm.opportunities.lose',
        'crm.activities.read',
        'crm.activities.record',
    ];

    public function up(): void
    {
        $roles = DB::table('identity.roles')->where('name', 'owner')->get();

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);
            $merged = array_values(array_unique(array_merge($permissions, self::CRM_PERMISSIONS)));

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
            $filtered = array_values(array_diff($permissions, self::CRM_PERMISSIONS));

            DB::table('identity.roles')
                ->where('id', $role->id)
                ->update(['permissions' => json_encode($filtered, JSON_THROW_ON_ERROR)]);
        }
    }
};
