<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->string('code', 80);
            $table->json('name');
            $table->json('description');
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['service_id', 'code']);
            $table->index(['service_id', 'is_active', 'deleted_at']);
        });

        Schema::create('service_package_components', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('service_package_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('description');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['service_package_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_package_components');
        Schema::dropIfExists('service_packages');
    }
};
