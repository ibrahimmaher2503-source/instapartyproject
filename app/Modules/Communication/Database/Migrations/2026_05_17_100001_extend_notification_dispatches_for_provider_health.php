<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table) {
            $table->string('provider_name', 60)->nullable()->after('error_message');
            $table->string('provider_message_id', 255)->nullable()->after('provider_name');
            $table->string('provider_status', 60)->nullable()->after('provider_message_id');
            $table->string('provider_error_code', 60)->nullable()->after('provider_status');
            $table->text('provider_error_message')->nullable()->after('provider_error_code');
            $table->unsignedTinyInteger('attempt_count')->default(0)->after('provider_error_message');
            $table->timestamp('last_attempt_at')->nullable()->after('attempt_count');
            $table->timestamp('next_retry_at')->nullable()->after('last_attempt_at');
            $table->boolean('is_test')->default(false)->after('next_retry_at');
            $table->timestamp('updated_at')->nullable()->after('is_test');

            $table->index(['status', 'next_retry_at'], 'idx_dispatches_retry_scan');
            $table->index(['provider_name', 'status', 'created_at'], 'idx_dispatches_provider_name');
            $table->index(['is_test', 'created_at'], 'idx_dispatches_is_test');
        });
    }

    public function down(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table) {
            $table->dropIndex('idx_dispatches_retry_scan');
            $table->dropIndex('idx_dispatches_provider_name');
            $table->dropIndex('idx_dispatches_is_test');

            $table->dropColumn([
                'provider_name',
                'provider_message_id',
                'provider_status',
                'provider_error_code',
                'provider_error_message',
                'attempt_count',
                'last_attempt_at',
                'next_retry_at',
                'is_test',
                'updated_at',
            ]);
        });
    }
};
