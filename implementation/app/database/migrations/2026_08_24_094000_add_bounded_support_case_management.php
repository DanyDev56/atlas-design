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
        Schema::table('operations.support_cases', function (Blueprint $table): void {
            $table->uuid('assigned_operator_user_id')->nullable()->after('requester_user_id');
            $table->unsignedInteger('revision')->default(1)->after('status');
            $table->index(['assigned_operator_user_id', 'status']);
        });

        Schema::create('operations.operator_action_idempotency', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('action_scope', 96);
            $table->string('idempotency_key', 128);
            $table->char('request_fingerprint', 64);
            $table->jsonb('response');
            $table->timestampTz('created_at');
            $table->unique(['action_scope', 'idempotency_key']);
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE operations.support_cases ADD CONSTRAINT operations_support_revision_check CHECK (revision >= 1)');
        DB::statement("ALTER TABLE operations.support_cases ADD CONSTRAINT operations_support_resolution_dates_check CHECK ((status NOT IN ('Resolved', 'Closed')) OR resolved_at IS NOT NULL)");
        DB::statement("ALTER TABLE operations.support_cases ADD CONSTRAINT operations_support_closure_date_check CHECK ((status <> 'Closed') OR closed_at IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('operations.operator_action_idempotency');
        DB::statement('ALTER TABLE operations.support_cases DROP CONSTRAINT IF EXISTS operations_support_revision_check');
        DB::statement('ALTER TABLE operations.support_cases DROP CONSTRAINT IF EXISTS operations_support_resolution_dates_check');
        DB::statement('ALTER TABLE operations.support_cases DROP CONSTRAINT IF EXISTS operations_support_closure_date_check');
        Schema::table('operations.support_cases', function (Blueprint $table): void {
            $table->dropIndex(['assigned_operator_user_id', 'status']);
            $table->dropColumn(['assigned_operator_user_id', 'revision']);
        });
    }
};
