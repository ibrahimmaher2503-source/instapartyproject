<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();

            $table->string('subject');
            $table->text('body');

            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])
                ->default('open');

            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'status'], 'support_tickets_user_status_idx');
            $table->index('status', 'support_tickets_status_idx');
            $table->index('email', 'support_tickets_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
