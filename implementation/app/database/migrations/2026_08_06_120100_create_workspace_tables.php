<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS workspace');

        if (! $this->tableExists('workspace', 'workspaces')) {
            Schema::create('workspace.workspaces', function ($table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('status', 32);
                $table->string('access_state', 32)->default('restricted');
                $table->unsignedInteger('version');
                $table->unsignedInteger('governance_version')->default(1);
                $table->uuid('requested_by_user_id')->nullable();
                $table->timestampTz('created_at');
                $table->timestampTz('updated_at');
                $table->timestampTz('activated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace.workspaces');
    }

    private function tableExists(string $schema, string $table): bool
    {
        $result = DB::selectOne(
            'SELECT to_regclass(?) IS NOT NULL AS exists',
            ["{$schema}.{$table}"],
        );

        return (bool) $result->exists;
    }
};
