<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.credit_notes', function (Blueprint $table): void {
            $table->string('original_number', 160)->nullable();
            $table->bigInteger('net_amount_cents')->nullable();
            $table->bigInteger('tax_amount_cents')->nullable();
            $table->bigInteger('gross_amount_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing.credit_notes', function (Blueprint $table): void {
            $table->dropColumn([
                'original_number', 'net_amount_cents', 'tax_amount_cents', 'gross_amount_cents',
            ]);
        });
    }
};
