<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'billing.credit-notes.read',
        'billing.credit-notes.create',
        'billing.credit-notes.update-draft',
        'billing.credit-notes.discard',
        'billing.credit-notes.issue',
        'billing.credit-notes.apply',
    ];

    public function up(): void
    {
        Schema::create('billing.credit_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('invoice_id');
            $table->uuid('client_id');
            $table->string('status', 32);
            $table->string('credit_note_number')->nullable();
            $table->jsonb('lines');
            $table->bigInteger('total_cents');
            $table->bigInteger('amount_applied_cents')->default(0);
            $table->bigInteger('unapplied_amount_cents')->default(0);
            $table->string('remainder_disposition', 32)->nullable();
            $table->string('currency', 3);
            $table->jsonb('client_snapshot');
            $table->text('reason')->nullable();
            $table->unsignedInteger('version');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('applied_at')->nullable();
            $table->timestampTz('discarded_at')->nullable();
            $table->boolean('is_historical_import')->default(false);
            $table->string('source_system', 64)->nullable();
            $table->string('external_id')->nullable();
            $table->index(['workspace_id', 'invoice_id']);
            $table->index(['workspace_id', 'status']);
            $table->unique(['workspace_id', 'credit_note_number']);
        });

        $this->updateOwnerPermissions(true);
    }

    public function down(): void
    {
        $this->updateOwnerPermissions(false);
        Schema::dropIfExists('billing.credit_notes');
    }

    private function updateOwnerPermissions(bool $grant): void
    {
        foreach (DB::table('identity.roles')->where('name', 'owner')->get() as $role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);
            $updated = $grant
                ? array_values(array_unique(array_merge($permissions, self::PERMISSIONS)))
                : array_values(array_diff($permissions, self::PERMISSIONS));

            DB::table('identity.roles')
                ->where('id', $role->id)
                ->update(['permissions' => json_encode($updated, JSON_THROW_ON_ERROR)]);
        }
    }
};
