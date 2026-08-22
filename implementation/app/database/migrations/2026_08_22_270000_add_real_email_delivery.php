<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity.email_verification_tokens', function (Blueprint $table): void {
            $table->text('delivery_secret')->nullable();
        });
        Schema::table('identity.account_recovery_tokens', function (Blueprint $table): void {
            $table->text('delivery_secret')->nullable();
        });
        Schema::table('identity.invitations', function (Blueprint $table): void {
            $table->text('delivery_secret')->nullable();
        });
        Schema::table('billing.public_document_proofs', function (Blueprint $table): void {
            $table->text('delivery_secret')->nullable();
        });

        Schema::create('platform.email_deliveries', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->string('event_type', 128);
            $table->string('template_key', 128);
            $table->string('template_version', 32);
            $table->string('recipient_fingerprint')->nullable();
            $table->string('status', 32);
            $table->string('provider', 64)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('updated_at');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform.email_deliveries');

        Schema::table('billing.public_document_proofs', function (Blueprint $table): void {
            $table->dropColumn('delivery_secret');
        });
        Schema::table('identity.invitations', function (Blueprint $table): void {
            $table->dropColumn('delivery_secret');
        });
        Schema::table('identity.account_recovery_tokens', function (Blueprint $table): void {
            $table->dropColumn('delivery_secret');
        });
        Schema::table('identity.email_verification_tokens', function (Blueprint $table): void {
            $table->dropColumn('delivery_secret');
        });
    }
};
