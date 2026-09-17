<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_uses', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->foreignId('promo_code_id')
                ->constrained('promo_codes')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            $table->unsignedBigInteger('discount_minor');
            $table->char('discount_currency', 3)->default('EGP');

            $table->timestamp('used_at')->useCurrent();

            $table->unique(['promo_code_id', 'booking_id'], 'uk_promo_use_per_booking');
            $table->index('user_id', 'promo_code_uses_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_uses');
    }
};
