<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Enums\AdminQueue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (AdminQueue::cases() as $queue) {
            DB::table('app_settings')->insertOrIgnore([
                'key' => $queue->settingKey(),
                'value' => json_encode(48, JSON_THROW_ON_ERROR),
                'description' => 'Admin queue SLA target in hours',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve admin-configured values when rolling back this data-only defaults migration.
    }
};
