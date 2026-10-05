<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governorates', function (Blueprint $table): void {
            $table->unsignedBigInteger('transport_fee_minor')->default(0)->after('is_active');
            $table->char('transport_fee_currency', 3)->default('EGP')->after('transport_fee_minor');
        });
    }

    public function down(): void
    {
        Schema::table('governorates', function (Blueprint $table): void {
            $table->dropColumn(['transport_fee_minor', 'transport_fee_currency']);
        });
    }
};
