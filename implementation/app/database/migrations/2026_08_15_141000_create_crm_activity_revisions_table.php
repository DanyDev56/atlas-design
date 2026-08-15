<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm.activity_revisions', function ($table): void {
            $table->uuid('activity_id');
            $table->uuid('workspace_id');
            $table->unsignedInteger('revision');
            $table->string('kind', 32);
            $table->text('summary');
            $table->timestampTz('occurred_at');
            $table->text('correction_reason');
            $table->uuid('corrected_by');
            $table->timestampTz('corrected_at');
            $table->primary(['activity_id', 'revision']);
            $table->index(['workspace_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm.activity_revisions');
    }
};
