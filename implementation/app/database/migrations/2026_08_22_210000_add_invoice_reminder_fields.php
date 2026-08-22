<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.invoices', function (Blueprint $table): void {
            $table->timestampTz('last_reminded_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('billing.invoices', function (Blueprint $table): void {
            $table->dropColumn(['last_reminded_at', 'reminder_count']);
        });
    }
};
