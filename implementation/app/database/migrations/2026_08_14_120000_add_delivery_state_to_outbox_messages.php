<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform.outbox_messages', function ($table): void {
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at', 6)->nullable();
            $table->string('last_error')->nullable();
            $table->timestampTz('failed_at', 6)->nullable();
        });

        DB::statement(
            'UPDATE platform.outbox_messages SET available_at = created_at WHERE available_at IS NULL',
        );
        DB::statement(
            <<<'SQL'
            CREATE INDEX platform_outbox_pending_idx
            ON platform.outbox_messages (available_at, created_at)
            WHERE dispatched_at IS NULL AND failed_at IS NULL
            SQL,
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS platform.platform_outbox_pending_idx');

        Schema::table('platform.outbox_messages', function ($table): void {
            $table->dropColumn(['attempts', 'available_at', 'last_error', 'failed_at']);
        });
    }
};
