<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_modifications', function (Blueprint $table): void {
            $table->json('rejection_reason')->nullable()->after('vendor_explanation');
        });
    }

    public function down(): void
    {
        Schema::table('booking_modifications', function (Blueprint $table): void {
            $table->dropColumn('rejection_reason');
        });
    }
};
