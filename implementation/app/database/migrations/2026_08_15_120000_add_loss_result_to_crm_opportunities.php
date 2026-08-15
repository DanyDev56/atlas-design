<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm.opportunities', function (Blueprint $table): void {
            $table->string('loss_reason_code', 32)->nullable();
            $table->string('loss_note', 500)->nullable();
            $table->timestampTz('lost_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm.opportunities', function (Blueprint $table): void {
            $table->dropColumn(['loss_reason_code', 'loss_note', 'lost_at']);
        });
    }
};
