<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm.activities', function (Blueprint $table): void {
            $table->text('removal_reason')->nullable();
            $table->uuid('removed_by')->nullable();
            $table->timestampTz('removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm.activities', function (Blueprint $table): void {
            $table->dropColumn(['removal_reason', 'removed_by', 'removed_at']);
        });
    }
};
