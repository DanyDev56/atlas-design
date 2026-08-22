<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing.document_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('document_type', 32);
            $table->uuid('document_id');
            $table->unsignedInteger('document_version');
            $table->string('filename');
            $table->string('media_type', 64)->default('application/pdf');
            $table->binary('content');
            $table->string('content_hash', 64);
            $table->timestampTz('created_at');
            $table->unique(
                ['workspace_id', 'document_type', 'document_id', 'document_version'],
                'billing_document_artifacts_document_version_unique',
            );
            $table->index(['workspace_id', 'document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing.document_artifacts');
    }
};
