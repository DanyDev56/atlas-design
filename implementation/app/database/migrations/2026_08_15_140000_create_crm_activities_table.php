<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm.activities', function ($table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('client_id');
            $table->uuid('contact_id')->nullable();
            $table->uuid('opportunity_id')->nullable();
            $table->string('kind', 32);
            $table->text('summary');
            $table->timestampTz('occurred_at');
            $table->string('status', 32);
            $table->unsignedInteger('version');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['workspace_id', 'client_id', 'status', 'occurred_at']);
            $table->index(['workspace_id', 'contact_id']);
            $table->index(['workspace_id', 'opportunity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm.activities');
    }
};
