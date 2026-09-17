<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_badge_assignments', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->foreignId('vendor_profile_id')
                ->constrained('vendor_profiles')
                ->cascadeOnDelete();

            $table->foreignId('trust_badge_id')
                ->constrained('trust_badges')
                ->cascadeOnDelete();

            $table->timestamp('assigned_at')->useCurrent();

            $table->foreignId('assigned_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->unique(['vendor_profile_id', 'trust_badge_id'], 'uk_vendor_badge');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_badge_assignments');
    }
};
