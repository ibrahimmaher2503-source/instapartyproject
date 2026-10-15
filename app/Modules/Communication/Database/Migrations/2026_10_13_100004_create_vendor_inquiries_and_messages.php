<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->string('customer_name');
            $table->char('service_public_id', 26);
            $table->string('service_name_en');
            $table->string('service_name_ar');
            $table->string('vendor_name_en');
            $table->string('vendor_name_ar');
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
            $table->index(['vendor_profile_id', 'created_at']);
        });

        Schema::create('vendor_inquiry_messages', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('vendor_inquiry_id')->constrained('vendor_inquiries')->restrictOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('author_role', ['customer', 'vendor']);
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['vendor_inquiry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_inquiry_messages');
        Schema::dropIfExists('vendor_inquiries');
    }
};
