<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->string('code', 50)->unique();
            $table->enum('type', ['percentage', 'fixed']);

            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->unsignedBigInteger('discount_minor')->nullable();
            $table->char('discount_currency', 3)->default('EGP');

            $table->unsignedBigInteger('min_order_minor')->nullable();
            $table->char('min_order_currency', 3)->default('EGP');

            $table->unsignedBigInteger('max_discount_minor')->nullable();
            $table->char('max_discount_currency', 3)->default('EGP');

            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->enum('scope', ['platform', 'vendor', 'category', 'service'])
                ->default('platform');
            $table->unsignedBigInteger('scope_id')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['code', 'is_active'], 'promo_codes_code_active_idx');
            $table->index(['scope', 'scope_id'], 'promo_codes_scope_idx');
            $table->index(['expires_at', 'is_active'], 'promo_codes_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
