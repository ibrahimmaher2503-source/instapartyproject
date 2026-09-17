<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_provider_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 30)->unique();
            $table->boolean('enabled')->default(true);
            $table->string('project_id', 190)->nullable();
            $table->longText('credentials')->nullable();
            $table->string('username', 190)->nullable();
            $table->text('password')->nullable();
            $table->string('sender_id', 120)->nullable();
            $table->unsignedTinyInteger('environment')->nullable();
            $table->string('last_connection_status', 30)->nullable();
            $table->text('last_connection_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_provider_settings');
    }
};
