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
        Schema::create('operations.beta_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('beta_code', 16)->unique();
            $table->uuid('user_id')->unique();
            $table->uuid('workspace_id')->unique();
            $table->string('pricing_cell', 8);
            $table->string('packaging_version', 64);
            $table->string('segment', 32);
            $table->string('channel', 32);
            $table->string('status', 16)->default('Active');
            $table->timestampTz('invited_at');
            $table->timestampTz('enrolled_at');
            $table->timestampTz('exited_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['status', 'invited_at']);
        });

        Schema::create('operations.beta_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('participant_id');
            $table->string('milestone', 8);
            $table->string('blockage_code', 64)->nullable();
            $table->unsignedInteger('support_minutes')->default(0);
            $table->string('next_action', 96);
            $table->timestampTz('reviewed_at');
            $table->timestampTz('created_at');
            $table->unique(['participant_id', 'milestone']);
            $table->index(['milestone', 'reviewed_at']);
        });

        Schema::create('operations.pricing_observations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('participant_id')->unique();
            $table->date('observed_on');
            $table->string('decision', 32);
            $table->string('primary_reason', 32);
            $table->string('preference', 16);
            $table->string('evidence_ref', 128)->nullable();
            $table->timestampTz('created_at');
            $table->index(['decision', 'observed_on']);
        });

        DB::statement('ALTER TABLE operations.beta_reviews ADD CONSTRAINT beta_reviews_participant_fk FOREIGN KEY (participant_id) REFERENCES operations.beta_participants(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE operations.pricing_observations ADD CONSTRAINT pricing_observations_participant_fk FOREIGN KEY (participant_id) REFERENCES operations.beta_participants(id) ON DELETE RESTRICT');

        DB::statement("ALTER TABLE operations.beta_participants ADD CONSTRAINT beta_participants_code_check CHECK (beta_code ~ '^BETA-[0-9]{3}$')");
        DB::statement("ALTER TABLE operations.beta_participants ADD CONSTRAINT beta_participants_cell_check CHECK (pricing_cell IN ('P19', 'P24', 'P29'))");
        DB::statement("ALTER TABLE operations.beta_participants ADD CONSTRAINT beta_participants_status_check CHECK (status IN ('Active', 'Exited'))");
        DB::statement("ALTER TABLE operations.beta_reviews ADD CONSTRAINT beta_reviews_milestone_check CHECK (milestone IN ('J2', 'J7', 'J14', 'J21', 'J30'))");
        DB::statement("ALTER TABLE operations.pricing_observations ADD CONSTRAINT pricing_observations_decision_check CHECK (decision IN ('PAID', 'PREORDERED', 'TRIAL_COMMITTED', 'DECLINED_PRICE', 'DECLINED_VALUE', 'DECLINED_SCOPE', 'DECLINED_TRUST', 'INELIGIBLE'))");
        DB::statement("ALTER TABLE operations.pricing_observations ADD CONSTRAINT pricing_observations_preference_check CHECK (preference IN ('Monthly', 'Annual', 'Indifferent'))");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION operations.reject_beta_pricing_cell_change()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.pricing_cell <> OLD.pricing_cell THEN
                    RAISE EXCEPTION 'A beta participant pricing cell is immutable';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER operations_beta_pricing_cell_immutable
            BEFORE UPDATE ON operations.beta_participants
            FOR EACH ROW
            EXECUTE FUNCTION operations.reject_beta_pricing_cell_change();

            CREATE OR REPLACE FUNCTION operations.reject_beta_evidence_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Beta research evidence is append-only';
            END;
            $$;

            CREATE TRIGGER operations_beta_reviews_append_only
            BEFORE UPDATE OR DELETE ON operations.beta_reviews
            FOR EACH ROW
            EXECUTE FUNCTION operations.reject_beta_evidence_mutation();

            CREATE TRIGGER operations_pricing_observations_append_only
            BEFORE UPDATE OR DELETE ON operations.pricing_observations
            FOR EACH ROW
            EXECUTE FUNCTION operations.reject_beta_evidence_mutation();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operations.pricing_observations');
        Schema::dropIfExists('operations.beta_reviews');
        Schema::dropIfExists('operations.beta_participants');
        DB::statement('DROP FUNCTION IF EXISTS operations.reject_beta_evidence_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS operations.reject_beta_pricing_cell_change()');
    }
};
