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
        Schema::create('operations.support_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 20)->unique();
            $table->uuid('workspace_id');
            $table->uuid('requester_user_id');
            $table->string('category', 24);
            $table->string('severity', 2);
            $table->string('status', 24);
            $table->boolean('requester_verified');
            $table->boolean('ownership_verified');
            $table->string('summary_code', 64);
            $table->timestampTz('response_due_at');
            $table->timestampTz('opened_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['status', 'response_due_at']);
            $table->index(['severity', 'opened_at']);
            $table->index(['workspace_id', 'opened_at']);
        });

        Schema::create('operations.support_case_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('support_case_id');
            $table->string('event_type', 32);
            $table->string('status', 24);
            $table->uuid('actor_operator_user_id')->nullable();
            $table->string('detail_code', 64)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');
            $table->index(['support_case_id', 'occurred_at']);
        });

        Schema::create('operations.data_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 20)->unique();
            $table->uuid('support_case_id')->unique();
            $table->uuid('workspace_id');
            $table->uuid('requester_user_id');
            $table->string('request_type', 24);
            $table->string('status', 24);
            $table->timestampTz('identity_verified_at')->nullable();
            $table->timestampTz('ownership_verified_at')->nullable();
            $table->timestampTz('due_at');
            $table->string('decision_code', 64)->nullable();
            $table->char('evidence_fingerprint', 64)->nullable();
            $table->timestampTz('delivery_expires_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['status', 'due_at']);
            $table->index(['request_type', 'created_at']);
        });

        Schema::create('operations.policy_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('document_kind', 24);
            $table->string('version', 64);
            $table->string('lifecycle', 16);
            $table->char('content_fingerprint', 64);
            $table->char('approval_fingerprint', 64)->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampTz('recorded_at');
            $table->unique(['document_kind', 'version']);
            $table->index(['document_kind', 'lifecycle', 'recorded_at']);
        });

        Schema::create('operations.policy_acknowledgements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('policy_version_id');
            $table->uuid('user_id');
            $table->uuid('workspace_id')->nullable();
            $table->string('proof_type', 16);
            $table->char('evidence_fingerprint', 64);
            $table->timestampTz('occurred_at');
            $table->timestampTz('recorded_at');
            $table->unique(['policy_version_id', 'user_id', 'proof_type']);
        });

        Schema::create('operations.research_consent_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('workspace_id')->nullable();
            $table->string('purpose', 24);
            $table->string('decision', 16);
            $table->char('evidence_fingerprint', 64);
            $table->timestampTz('occurred_at');
            $table->timestampTz('recorded_at');
            $table->index(['purpose', 'occurred_at']);
            $table->index(['user_id', 'purpose', 'occurred_at']);
        });

        DB::statement('ALTER TABLE operations.support_case_events ADD CONSTRAINT operations_support_event_case_fk FOREIGN KEY (support_case_id) REFERENCES operations.support_cases(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE operations.data_requests ADD CONSTRAINT operations_data_request_case_fk FOREIGN KEY (support_case_id) REFERENCES operations.support_cases(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE operations.policy_acknowledgements ADD CONSTRAINT operations_policy_ack_version_fk FOREIGN KEY (policy_version_id) REFERENCES operations.policy_versions(id) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE operations.support_cases ADD CONSTRAINT operations_support_reference_check CHECK (reference ~ '^SUP-[A-Z0-9]{12}$')");
        DB::statement("ALTER TABLE operations.data_requests ADD CONSTRAINT operations_data_reference_check CHECK (reference ~ '^DR-[A-Z0-9]{12}$')");
        DB::statement("ALTER TABLE operations.support_cases ADD CONSTRAINT operations_support_category_check CHECK (category IN ('Access', 'Security', 'Billing', 'DataRequest', 'Product', 'Other'))");
        DB::statement("ALTER TABLE operations.support_cases ADD CONSTRAINT operations_support_severity_check CHECK (severity IN ('P0', 'P1', 'P2', 'P3'))");
        DB::statement("ALTER TABLE operations.support_cases ADD CONSTRAINT operations_support_status_check CHECK (status IN ('Open', 'Acknowledged', 'InProgress', 'WaitingRequester', 'Resolved', 'Closed'))");
        DB::statement("ALTER TABLE operations.support_case_events ADD CONSTRAINT operations_support_event_type_check CHECK (event_type IN ('Opened', 'StatusChanged', 'AssignmentChanged', 'VerificationRecorded', 'DecisionRecorded'))");
        DB::statement("ALTER TABLE operations.support_case_events ADD CONSTRAINT operations_support_event_status_check CHECK (status IN ('Open', 'Acknowledged', 'InProgress', 'WaitingRequester', 'Resolved', 'Closed'))");
        DB::statement("ALTER TABLE operations.data_requests ADD CONSTRAINT operations_data_type_check CHECK (request_type IN ('Access', 'Rectification', 'Erasure', 'Restriction', 'Objection', 'Portability'))");
        DB::statement("ALTER TABLE operations.data_requests ADD CONSTRAINT operations_data_status_check CHECK (status IN ('Received', 'IdentityPending', 'Qualified', 'InPreparation', 'AwaitingApproval', 'Delivered', 'Rejected', 'Closed'))");
        DB::statement("ALTER TABLE operations.policy_versions ADD CONSTRAINT operations_policy_kind_check CHECK (document_kind IN ('BetaTerms', 'PrivacyNotice'))");
        DB::statement("ALTER TABLE operations.policy_versions ADD CONSTRAINT operations_policy_lifecycle_check CHECK (lifecycle IN ('Draft', 'Published'))");
        DB::statement("ALTER TABLE operations.policy_acknowledgements ADD CONSTRAINT operations_policy_proof_type_check CHECK (proof_type IN ('Informed', 'Accepted'))");
        DB::statement("ALTER TABLE operations.research_consent_events ADD CONSTRAINT operations_consent_purpose_check CHECK (purpose IN ('Interview', 'Recording', 'PublicQuote'))");
        DB::statement("ALTER TABLE operations.research_consent_events ADD CONSTRAINT operations_consent_decision_check CHECK (decision IN ('Granted', 'Withdrawn'))");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION operations.reject_compliance_evidence_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Support and compliance evidence is append-only';
            END;
            $$;

            CREATE TRIGGER operations_support_case_events_append_only
            BEFORE UPDATE OR DELETE ON operations.support_case_events
            FOR EACH ROW EXECUTE FUNCTION operations.reject_compliance_evidence_mutation();

            CREATE TRIGGER operations_policy_versions_append_only
            BEFORE UPDATE OR DELETE ON operations.policy_versions
            FOR EACH ROW EXECUTE FUNCTION operations.reject_compliance_evidence_mutation();

            CREATE TRIGGER operations_policy_acknowledgements_append_only
            BEFORE UPDATE OR DELETE ON operations.policy_acknowledgements
            FOR EACH ROW EXECUTE FUNCTION operations.reject_compliance_evidence_mutation();

            CREATE TRIGGER operations_research_consent_events_append_only
            BEFORE UPDATE OR DELETE ON operations.research_consent_events
            FOR EACH ROW EXECUTE FUNCTION operations.reject_compliance_evidence_mutation();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operations.research_consent_events');
        Schema::dropIfExists('operations.policy_acknowledgements');
        Schema::dropIfExists('operations.policy_versions');
        Schema::dropIfExists('operations.data_requests');
        Schema::dropIfExists('operations.support_case_events');
        Schema::dropIfExists('operations.support_cases');
        DB::statement('DROP FUNCTION IF EXISTS operations.reject_compliance_evidence_mutation()');
    }
};
