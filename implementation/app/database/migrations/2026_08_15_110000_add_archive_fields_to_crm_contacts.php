<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm.contacts', function (Blueprint $table): void {
            $table->string('archive_reason')->nullable();
            $table->timestampTz('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm.contacts', function (Blueprint $table): void {
            $table->dropColumn(['archive_reason', 'archived_at']);
        });
    }
};
