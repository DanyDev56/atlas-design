<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions.recurring_subscriptions', function ($table): void {
            $table->timestampTz('past_due_since')->nullable()->after('canceled_at');
        });

        DB::table('subscriptions.recurring_subscriptions')
            ->where('status', 'PastDue')
            ->update(['past_due_since' => DB::raw('last_provider_event_at')]);
    }

    public function down(): void
    {
        Schema::table('subscriptions.recurring_subscriptions', function ($table): void {
            $table->dropColumn('past_due_since');
        });
    }
};
