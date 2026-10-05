<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('transport_base_minor')->nullable()->after('delivery_total_currency');
            $table->char('transport_base_currency', 3)->nullable()->after('transport_base_minor');
            $table->string('transport_base_source', 120)->nullable()->after('transport_base_currency');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['transport_base_minor', 'transport_base_currency', 'transport_base_source']);
        });
    }
};
