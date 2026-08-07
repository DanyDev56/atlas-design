<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->columnExists('billing', 'invoices', 'due_date')) {
            return;
        }

        Schema::table('billing.invoices', function ($table): void {
            $table->timestampTz('due_date')->nullable();
            $table->timestampTz('paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing.invoices', function ($table): void {
            $table->dropColumn(['due_date', 'paid_at']);
        });
    }

    private function columnExists(string $schema, string $table, string $column): bool
    {
        $result = DB::selectOne(
            <<<'SQL'
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = ? AND table_name = ? AND column_name = ?
            ) AS exists
            SQL,
            [$schema, $table, $column],
        );

        return (bool) $result->exists;
    }
};
