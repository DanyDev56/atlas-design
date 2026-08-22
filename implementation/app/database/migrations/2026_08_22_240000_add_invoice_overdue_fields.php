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
            $table->timestampTz('overdue_at')->nullable();
            $table->timestampTz('overdue_due_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing.invoices', function (Blueprint $table): void {
            $table->dropColumn(['overdue_at', 'overdue_due_date']);
        });
    }
};
