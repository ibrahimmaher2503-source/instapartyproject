<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique('idx_package_recommendations_public_id');
            $table->string('slug', 120)->unique('idx_package_recommendations_slug');
            $table->json('name');
            $table->json('description')->nullable();
            $table->foreignId('occasion_id')->nullable()->constrained('occasions')->restrictOnDelete();
            $table->unsignedBigInteger('min_budget_minor')->nullable();
            $table->unsignedBigInteger('max_budget_minor')->nullable();
            $table->char('budget_currency', 3)->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'display_order'], 'idx_pkg_rec_published_order');
            $table->index(['occasion_id', 'is_published'], 'idx_pkg_rec_occasion_pub');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_recommendations');
    }
};
