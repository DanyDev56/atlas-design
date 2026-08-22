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
        Schema::table('workspace.workspaces', function (Blueprint $table): void {
            $table->string('trading_name')->nullable();
            $table->text('activity_description')->nullable();
            $table->unsignedInteger('profile_version')->default(1);
            $table->string('legal_name')->nullable();
            $table->string('administrative_email')->nullable();
            $table->unsignedInteger('billing_identity_version')->default(1);
            $table->string('locale', 16)->default('fr-FR');
            $table->string('timezone', 64)->default('Europe/Paris');
            $table->string('default_currency', 8)->default('EUR');
            $table->string('establishment_country', 8)->default('FR');
            $table->unsignedInteger('preferences_version')->default(1);
        });

        if (! $this->tableExists('workspace', 'idempotency_keys')) {
            Schema::create('workspace.idempotency_keys', function (Blueprint $table): void {
                $table->string('scope');
                $table->string('key');
                $table->string('fingerprint');
                $table->jsonb('response_payload')->nullable();
                $table->timestampTz('created_at');
                $table->primary(['scope', 'key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace.idempotency_keys');
        Schema::table('workspace.workspaces', function (Blueprint $table): void {
            $table->dropColumn([
                'trading_name',
                'activity_description',
                'profile_version',
                'legal_name',
                'administrative_email',
                'billing_identity_version',
                'locale',
                'timezone',
                'default_currency',
                'establishment_country',
                'preferences_version',
            ]);
        });
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
