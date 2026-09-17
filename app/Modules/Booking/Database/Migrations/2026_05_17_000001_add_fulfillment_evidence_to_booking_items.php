<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_items', function (Blueprint $table): void {
            $table->text('completion_note')->nullable()->after('fulfillment_data');
            $table->foreignId('completion_photo_media_id')
                ->nullable()
                ->after('completion_note')
                ->constrained('media')
                ->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('completion_photo_media_id');
            $table->foreignId('completed_by_vendor_user_id')
                ->nullable()
                ->after('completed_at')
                ->constrained('users')
                ->restrictOnDelete();
            $table->index(['booking_vendor_id', 'completed_at'], 'bi_vendor_completed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table): void {
            $table->dropIndex('bi_vendor_completed_at_idx');
            $table->dropConstrainedForeignId('completed_by_vendor_user_id');
            $table->dropColumn('completed_at');
            $table->dropConstrainedForeignId('completion_photo_media_id');
            $table->dropColumn('completion_note');
        });
    }
};
