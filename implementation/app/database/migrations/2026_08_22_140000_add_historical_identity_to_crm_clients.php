<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm.clients', function ($table): void {
            $table->string('source_system', 64)->nullable();
            $table->string('external_id', 160)->nullable();
            $table->uuid('import_run_id')->nullable();
            $table->string('canonical_record_hash', 64)->nullable();
            $table->timestampTz('source_created_at')->nullable();
        });

        DB::statement(
            'CREATE UNIQUE INDEX crm_clients_historical_identity_unique
             ON crm.clients (workspace_id, source_system, external_id)
             WHERE source_system IS NOT NULL AND external_id IS NOT NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS crm.crm_clients_historical_identity_unique');

        Schema::table('crm.clients', function ($table): void {
            $table->dropColumn([
                'source_system',
                'external_id',
                'import_run_id',
                'canonical_record_hash',
                'source_created_at',
            ]);
        });
    }
};
