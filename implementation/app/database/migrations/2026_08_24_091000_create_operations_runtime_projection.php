<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations.runtime_heartbeats', function ($table): void {
            $table->string('role', 24)->primary();
            $table->timestampTz('recorded_at');
            $table->timestampTz('updated_at');
        });

        Schema::create('operations.maintenance_runs', function ($table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 32);
            $table->string('run_reference', 96);
            $table->string('status', 16);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at');
            $table->timestampTz('recorded_at');
            $table->unique(['kind', 'run_reference']);
            $table->index(['kind', 'completed_at']);
            $table->index(['status', 'completed_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE operations.runtime_heartbeats
            ADD CONSTRAINT operations_runtime_role_check
            CHECK (role IN ('api', 'worker', 'scheduler'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE operations.maintenance_runs
            ADD CONSTRAINT operations_maintenance_kind_check
            CHECK (kind IN ('Backup', 'RestoreCanary'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE operations.maintenance_runs
            ADD CONSTRAINT operations_maintenance_status_check
            CHECK (status IN ('Succeeded', 'Failed'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operations.maintenance_runs');
        Schema::dropIfExists('operations.runtime_heartbeats');
    }
};
