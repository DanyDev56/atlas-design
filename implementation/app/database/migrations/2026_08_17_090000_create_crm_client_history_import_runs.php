<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm.client_history_import_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('preview_id');
            $table->string('source_system', 64);
            $table->timestampTz('source_exported_at');
            $table->string('package_hash', 64);
            $table->string('status', 32);
            $table->unsignedSmallInteger('client_count')->default(0);
            $table->unsignedSmallInteger('processed_count')->default(0);
            $table->uuid('created_by');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'created_at']);
            $table->unique(['workspace_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm.client_history_import_runs');
    }
};
