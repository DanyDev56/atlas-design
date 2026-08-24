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
        Schema::table('operations.operator_sessions', function (Blueprint $table): void {
            $table->string('reference', 16)->nullable()->after('id');
            $table->unsignedInteger('revision')->default(1)->after('status');
        });

        DB::statement("UPDATE operations.operator_sessions SET reference = 'SES-' || UPPER(SUBSTRING(MD5(id::text), 1, 12)) WHERE reference IS NULL");
        DB::statement('ALTER TABLE operations.operator_sessions ALTER COLUMN reference SET NOT NULL');

        Schema::table('operations.operator_sessions', function (Blueprint $table): void {
            $table->unique('reference');
        });
        DB::statement("ALTER TABLE operations.operator_sessions ADD CONSTRAINT operations_operator_session_reference_check CHECK (reference ~ '^SES-[A-F0-9]{12}$')");
        DB::statement('ALTER TABLE operations.operator_sessions ADD CONSTRAINT operations_operator_session_revision_check CHECK (revision >= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE operations.operator_sessions DROP CONSTRAINT IF EXISTS operations_operator_session_reference_check');
        DB::statement('ALTER TABLE operations.operator_sessions DROP CONSTRAINT IF EXISTS operations_operator_session_revision_check');
        Schema::table('operations.operator_sessions', function (Blueprint $table): void {
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'revision']);
        });
    }
};
