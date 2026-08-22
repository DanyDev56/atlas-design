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
        Schema::table('billing.invoices', function (Blueprint $table): void {
            $table->string('kind', 32)->default('Final');
        });

        DB::statement(
            'CREATE UNIQUE INDEX billing_invoices_quote_kind_unique
             ON billing.invoices (workspace_id, quote_id, kind)
             WHERE quote_id IS NOT NULL AND is_historical_import = false',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS billing.billing_invoices_quote_kind_unique');
        Schema::table('billing.invoices', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
