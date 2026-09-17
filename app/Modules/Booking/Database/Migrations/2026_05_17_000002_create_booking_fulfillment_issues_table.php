<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_fulfillment_issues', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('booking_vendor_id')
                ->constrained('booking_vendors')
                ->cascadeOnDelete();
            $table->foreignId('booking_item_id')
                ->nullable()
                ->constrained('booking_items')
                ->nullOnDelete();
            $table->foreignId('reported_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('reason_code', [
                'venue_unavailable',
                'customer_unreachable',
                'damaged_goods',
                'safety_concern',
                'other',
            ]);
            $table->text('note');

            $table->enum('status', ['open', 'acknowledged', 'resolved', 'dismissed'])
                ->default('open');

            $table->foreignId('acknowledged_by_admin_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['booking_vendor_id', 'status'], 'bfi_vendor_status_idx');
            $table->index(['reported_by_user_id', 'created_at'], 'bfi_reporter_created_idx');
            $table->index(['status', 'created_at'], 'bfi_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_fulfillment_issues');
    }
};
