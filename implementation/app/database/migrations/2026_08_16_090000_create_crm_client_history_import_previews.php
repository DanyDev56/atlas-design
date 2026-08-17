<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm.client_history_import_previews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('source_system', 64);
            $table->timestampTz('source_exported_at');
            $table->string('schema_version', 16);
            $table->string('package_hash', 64);
            $table->unsignedSmallInteger('row_count');
            $table->unsignedSmallInteger('valid_row_count');
            $table->unsignedSmallInteger('validation_error_count');
            $table->jsonb('records');
            $table->jsonb('validation_errors');
            $table->jsonb('duplicate_candidates');
            $table->uuid('created_by');
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'package_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm.client_history_import_previews');
    }
};
