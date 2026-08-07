<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BILLING_PERMISSIONS = [
        'billing.quotes.read',
        'billing.quotes.create',
        'billing.quotes.update-draft',
        'billing.quotes.send',
        'billing.quotes.withdraw',
        'billing.invoices.read',
        'billing.invoices.create',
        'billing.invoices.update-draft',
        'billing.invoices.discard',
        'billing.invoices.issue',
        'billing.invoices.correct-metadata',
        'billing.invoices.send',
        'billing.invoices.remind',
        'billing.payments.read',
        'billing.payments.record',
        'billing.payments.reverse',
    ];

    public function up(): void
    {
        $roles = DB::table('identity.roles')->where('name', 'owner')->get();

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);
            $merged = array_values(array_unique(array_merge($permissions, self::BILLING_PERMISSIONS)));

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
            $filtered = array_values(array_diff($permissions, self::BILLING_PERMISSIONS));

            DB::table('identity.roles')
                ->where('id', $role->id)
                ->update(['permissions' => json_encode($filtered, JSON_THROW_ON_ERROR)]);
        }
    }
};
