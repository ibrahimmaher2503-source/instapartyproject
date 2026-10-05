<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_rental_details', function (Blueprint $table): void {
            // Unknown historical dimensions stay null; legacy fields are retained.
            $table->decimal('length_m', 8, 2)->nullable();
            $table->decimal('width_m', 8, 2)->nullable();
            $table->decimal('height_m', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_rental_details', function (Blueprint $table): void {
            $table->dropColumn(['length_m', 'width_m', 'height_m']);
        });
    }
};
