<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace.workspaces', function ($table): void {
            if (! Schema::hasColumn('workspace.workspaces', 'access_state')) {
                $table->string('access_state', 32)->default('restricted')->after('status');
            }
            if (! Schema::hasColumn('workspace.workspaces', 'governance_version')) {
                $table->unsignedInteger('governance_version')->default(1)->after('version');
            }
            if (! Schema::hasColumn('workspace.workspaces', 'requested_by_user_id')) {
                $table->uuid('requested_by_user_id')->nullable()->after('governance_version');
            }
            if (! Schema::hasColumn('workspace.workspaces', 'activated_at')) {
                $table->timestampTz('activated_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workspace.workspaces', function ($table): void {
            $table->dropColumn(['access_state', 'governance_version', 'requested_by_user_id', 'activated_at']);
        });
    }
};
