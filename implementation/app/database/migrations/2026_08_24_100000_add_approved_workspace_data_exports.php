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
        Schema::create('operations.data_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 20)->unique();
            $table->uuid('data_request_id');
            $table->uuid('workspace_id');
            $table->uuid('requester_user_id');
            $table->string('scope', 32);
            $table->string('status', 24);
            $table->uuid('requested_by_operator_user_id');
            $table->uuid('approved_by_operator_user_id')->nullable();
            $table->string('reason_code', 64);
            $table->unsignedInteger('revision')->default(1);
            $table->timestampTz('requested_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('downloaded_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique('data_request_id');
            $table->index(['status', 'requested_at']);
            $table->index(['expires_at', 'status']);
        });

        Schema::create('operations.data_export_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('data_export_id')->unique();
            $table->text('ciphertext');
            $table->char('content_fingerprint', 64);
            $table->unsignedBigInteger('byte_size');
            $table->string('media_type', 80);
            $table->timestampTz('generated_at');
            $table->timestampTz('expires_at');
        });

        Schema::create('operations.data_export_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('data_export_id');
            $table->string('event_type', 32);
            $table->string('status', 24);
            $table->uuid('actor_operator_user_id')->nullable();
            $table->string('detail_code', 64)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');
            $table->index(['data_export_id', 'occurred_at']);
        });

        DB::statement('ALTER TABLE operations.data_exports ADD CONSTRAINT operations_data_export_request_fk FOREIGN KEY (data_request_id) REFERENCES operations.data_requests(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE operations.data_export_artifacts ADD CONSTRAINT operations_data_export_artifact_fk FOREIGN KEY (data_export_id) REFERENCES operations.data_exports(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE operations.data_export_events ADD CONSTRAINT operations_data_export_event_fk FOREIGN KEY (data_export_id) REFERENCES operations.data_exports(id) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE operations.data_exports ADD CONSTRAINT operations_data_export_reference_check CHECK (reference ~ '^EXP-[A-Z0-9]{12}$')");
        DB::statement("ALTER TABLE operations.data_exports ADD CONSTRAINT operations_data_export_scope_check CHECK (scope IN ('WorkspaceDataV1'))");
        DB::statement("ALTER TABLE operations.data_exports ADD CONSTRAINT operations_data_export_status_check CHECK (status IN ('AwaitingApproval', 'Generating', 'Ready', 'Delivered', 'Expired', 'Failed'))");
        DB::statement("ALTER TABLE operations.data_export_events ADD CONSTRAINT operations_data_export_event_type_check CHECK (event_type IN ('Requested', 'Approved', 'Generated', 'Downloaded', 'Expired', 'Failed'))");
        DB::statement("ALTER TABLE operations.data_export_events ADD CONSTRAINT operations_data_export_event_status_check CHECK (status IN ('AwaitingApproval', 'Generating', 'Ready', 'Delivered', 'Expired', 'Failed'))");
        DB::statement('ALTER TABLE operations.data_exports ADD CONSTRAINT operations_data_export_revision_check CHECK (revision >= 1)');
        DB::statement('ALTER TABLE operations.data_exports ADD CONSTRAINT operations_data_export_distinct_approval_check CHECK (approved_by_operator_user_id IS NULL OR approved_by_operator_user_id <> requested_by_operator_user_id)');
        DB::statement("ALTER TABLE operations.data_exports ADD CONSTRAINT operations_data_export_ready_dates_check CHECK ((status NOT IN ('Ready', 'Delivered')) OR (ready_at IS NOT NULL AND expires_at IS NOT NULL))");

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER operations_data_export_events_append_only
            BEFORE UPDATE OR DELETE ON operations.data_export_events
            FOR EACH ROW EXECUTE FUNCTION operations.reject_compliance_evidence_mutation();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operations.data_export_artifacts');
        Schema::dropIfExists('operations.data_export_events');
        Schema::dropIfExists('operations.data_exports');
    }
};
