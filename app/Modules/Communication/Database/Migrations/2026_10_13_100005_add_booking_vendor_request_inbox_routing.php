<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('admin_inbox_routing_rules')->where('event_key', 'booking.vendor_request.pending')->exists()) {
            return;
        }

        foreach (DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['admin', 'booking_manager', 'super_admin'])
            ->pluck('id') as $roleId) {
            DB::table('admin_inbox_routing_rules')->insert([
                'public_id' => (string) Str::ulid(),
                'event_key' => 'booking.vendor_request.pending',
                'severity' => 'warning',
                'route_to_role_id' => $roleId,
                'route_to_admin_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep routing rules administrators may have edited after installation.
    }
};
