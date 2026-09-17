<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_wishlists', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('vendor_profile_id');
            $table->timestamps();

            // Foreign keys — cascade on delete: wishlist entries are owned by the user and vendor
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('vendor_profile_id')->references('id')->on('vendor_profiles')->cascadeOnDelete();

            // Prevent duplicate saves
            $table->unique(['user_id', 'vendor_profile_id']);

            // Chronological listing per customer
            $table->index(['user_id', 'created_at']);

            // Analytics — how many customers saved a vendor
            $table->index('vendor_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_wishlists');
    }
};
