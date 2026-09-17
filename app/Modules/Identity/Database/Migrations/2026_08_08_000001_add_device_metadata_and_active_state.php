<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table): void {
            $table->string('device_name', 120)->nullable()->after('device_id');
            $table->string('app_version', 40)->nullable()->after('device_name');
            $table->timestamp('last_used_at')->nullable()->after('last_seen_at');
            $table->boolean('is_active')->default(true)->after('last_used_at');
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropColumn(['device_name', 'app_version', 'last_used_at', 'is_active']);
        });
    }
};
