<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_items', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('faq_category_id')
                ->constrained('faq_categories')
                ->cascadeOnDelete();

            $table->json('question');
            $table->json('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['faq_category_id', 'is_active', 'sort_order'], 'faq_items_category_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
    }
};
