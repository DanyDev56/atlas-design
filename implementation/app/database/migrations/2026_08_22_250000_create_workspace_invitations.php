<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity.invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('recipient_email');
            $table->string('recipient_email_fingerprint');
            $table->uuid('role_id');
            $table->uuid('invited_by');
            $table->string('status', 32);
            $table->string('delivery_status', 32);
            $table->string('token_hash')->unique();
            $table->timestampTz('expires_at');
            $table->uuid('accepted_by')->nullable();
            $table->uuid('membership_id')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unsignedInteger('version')->default(1);
            $table->index(['workspace_id', 'status']);
            $table->index(['recipient_email_fingerprint', 'status']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX identity_invitations_pending_recipient_unique
             ON identity.invitations (workspace_id, recipient_email)
             WHERE status = 'Pending'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.invitations');
    }
};
