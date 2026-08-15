<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advisor.recommendations', function ($table): void {
            $table->unsignedInteger('revision')->default(1);
            $table->jsonb('terminal_decision')->nullable();
            $table->timestampTz('terminal_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('advisor.recommendations', function ($table): void {
            $table->dropColumn(['revision', 'terminal_decision', 'terminal_at']);
        });
    }
};
