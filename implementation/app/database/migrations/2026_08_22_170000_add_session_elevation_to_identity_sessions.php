<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity.sessions', function (Blueprint $table): void {
            $table->string('elevation_status', 32)->nullable();
            $table->string('elevation_scope', 32)->nullable();
            $table->jsonb('elevation_permissions')->nullable();
            $table->timestampTz('elevation_expires_at')->nullable();
            $table->unsignedInteger('elevation_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('identity.sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'elevation_status',
                'elevation_scope',
                'elevation_permissions',
                'elevation_expires_at',
                'elevation_version',
            ]);
        });
    }
};
