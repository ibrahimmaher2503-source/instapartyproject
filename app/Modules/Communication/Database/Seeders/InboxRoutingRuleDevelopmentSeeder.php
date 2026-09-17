<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class InboxRoutingRuleDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::query()->where('name', 'super_admin')->first();
        $adminRole = Role::query()->where('name', 'admin')->first();
        $admin = User::query()->where('email', 'admin@instaparty.local')->first();

        $rows = [
            [
                'event_key' => 'booking.payment_failed',
                'severity' => AdminInboxSeverity::Warning,
                'route_to_role_id' => $adminRole?->id,
                'route_to_admin_id' => null,
            ],
            [
                'event_key' => 'vendor.document_rejected',
                'severity' => AdminInboxSeverity::Info,
                'route_to_role_id' => $adminRole?->id,
                'route_to_admin_id' => null,
            ],
            [
                'event_key' => 'reconciliation.findings_detected',
                'severity' => AdminInboxSeverity::Critical,
                'route_to_role_id' => $superAdminRole?->id,
                'route_to_admin_id' => $admin?->id,
            ],
            [
                'event_key' => 'vendor.auto_suspended',
                'severity' => AdminInboxSeverity::Critical,
                'route_to_role_id' => $adminRole?->id,
                'route_to_admin_id' => null,
            ],
            [
                'event_key' => 'vendor.approval.sla_24h',
                'severity' => AdminInboxSeverity::Warning,
                'route_to_role_id' => $adminRole?->id,
                'route_to_admin_id' => null,
            ],
            [
                'event_key' => 'vendor.approval.sla_48h',
                'severity' => AdminInboxSeverity::Critical,
                'route_to_role_id' => $adminRole?->id,
                'route_to_admin_id' => null,
            ],
            [
                'event_key' => 'trust_safety.report_submitted',
                'severity' => AdminInboxSeverity::Warning,
                'route_to_role_id' => $adminRole?->id,
                'route_to_admin_id' => null,
            ],
            [
                'event_key' => 'refund.failed',
                'severity' => AdminInboxSeverity::Critical,
                'route_to_role_id' => $adminRole?->id,
                'route_to_admin_id' => null,
            ],
            [
                'event_key' => 'chargeback.opened',
                'severity' => AdminInboxSeverity::Critical,
                'route_to_role_id' => $adminRole?->id,
                'route_to_admin_id' => null,
            ],
            [
                'event_key' => 'booking.vendor_response_timeout',
                'severity' => AdminInboxSeverity::Critical,
                'route_to_role_id' => $adminRole?->id,
                'route_to_admin_id' => null,
            ],
            [
                'event_key' => 'vendor.document.uploaded',
                'severity' => AdminInboxSeverity::Info,
                'route_to_role_id' => $adminRole?->id,
                'route_to_admin_id' => null,
            ],
        ];

        foreach ($rows as $row) {
            AdminInboxRoutingRule::query()->updateOrCreate(
                ['event_key' => $row['event_key'], 'severity' => $row['severity']->value],
                array_merge($row, [
                    'public_id' => (string) Str::ulid(),
                    'is_active' => true,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ]),
            );
        }
    }
}
