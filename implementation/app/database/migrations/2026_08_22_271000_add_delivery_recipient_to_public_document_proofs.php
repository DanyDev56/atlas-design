<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.public_document_proofs', function (Blueprint $table): void {
            $table->text('delivery_recipient')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing.public_document_proofs', function (Blueprint $table): void {
            $table->dropColumn('delivery_recipient');
        });
    }
};
