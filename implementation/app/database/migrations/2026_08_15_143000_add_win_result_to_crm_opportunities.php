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
        Schema::table('crm.opportunities', function (Blueprint $table): void {
            $table->string('win_source', 32)->nullable();
            $table->uuid('won_quote_id')->nullable();
            $table->uuid('won_by')->nullable();
            $table->timestampTz('won_at')->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE crm.opportunities AS opportunities
            SET win_source = 'AcceptedQuote',
                won_quote_id = accepted_quotes.id,
                won_at = COALESCE(accepted_quotes.accepted_at, opportunities.updated_at)
            FROM (
                SELECT DISTINCT ON (workspace_id, opportunity_id)
                    id, workspace_id, opportunity_id, accepted_at
                FROM billing.quotes
                WHERE status = 'Accepted' AND opportunity_id IS NOT NULL
                ORDER BY workspace_id, opportunity_id, accepted_at DESC NULLS LAST
            ) AS accepted_quotes
            WHERE opportunities.workspace_id = accepted_quotes.workspace_id
              AND opportunities.id = accepted_quotes.opportunity_id
              AND opportunities.status = 'Won'
            SQL);
    }

    public function down(): void
    {
        Schema::table('crm.opportunities', function (Blueprint $table): void {
            $table->dropColumn(['win_source', 'won_quote_id', 'won_by', 'won_at']);
        });
    }
};
