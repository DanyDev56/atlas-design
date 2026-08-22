<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.history_import_previews', function (Blueprint $table): void {
            $table->unsignedInteger('credit_note_count')->default(0);
            $table->jsonb('credit_notes')->nullable();
        });

        Schema::table('billing.history_import_runs', function (Blueprint $table): void {
            $table->unsignedInteger('credit_note_count')->default(0);
            $table->unsignedInteger('processed_credit_notes')->default(0);
        });

        Schema::table('billing.credit_notes', function (Blueprint $table): void {
            $table->uuid('import_run_id')->nullable();
            $table->string('canonical_record_hash', 64)->nullable();
            $table->timestampTz('source_exported_at')->nullable();
        });

        Schema::table('analytics.historical_import_rebuilds', function (Blueprint $table): void {
            $table->unsignedInteger('credit_note_fact_count')->default(0);
        });

        DB::statement(
            'CREATE UNIQUE INDEX billing_credit_notes_historical_identity_unique
             ON billing.credit_notes (workspace_id, source_system, external_id)
             WHERE source_system IS NOT NULL AND external_id IS NOT NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS billing.billing_credit_notes_historical_identity_unique');

        Schema::table('analytics.historical_import_rebuilds', function (Blueprint $table): void {
            $table->dropColumn('credit_note_fact_count');
        });

        Schema::table('billing.credit_notes', function (Blueprint $table): void {
            $table->dropColumn(['import_run_id', 'canonical_record_hash', 'source_exported_at']);
        });

        Schema::table('billing.history_import_runs', function (Blueprint $table): void {
            $table->dropColumn(['credit_note_count', 'processed_credit_notes']);
        });

        Schema::table('billing.history_import_previews', function (Blueprint $table): void {
            $table->dropColumn(['credit_note_count', 'credit_notes']);
        });
    }
};
