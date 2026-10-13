<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RouteToAdminInboxAction
{
    /**
     * @param  array{en: string, ar: string}  $title
     * @param  array{en: string, ar: string}  $body
     */
    public function execute(
        string $eventKey,
        AdminInboxSeverity $severity,
        string $sourceType,
        int $sourceId,
        array $title,
        array $body,
    ): void {
        $isApprovalSla = in_array($eventKey, ['vendor.approval.sla_24h', 'vendor.approval.sla_48h'], true);
        $rules = AdminInboxRoutingRule::query()
            ->where('event_key', $eventKey)
            ->where('is_active', true)
            ->when(! $isApprovalSla, fn ($query) => $query->where('severity', $severity->value))
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        // Batch-load all referenced roles with their users in two queries.
        $roleIds = $rules->whereNotNull('route_to_role_id')->pluck('route_to_role_id')->unique();
        $roleUsers = $roleIds->isNotEmpty()
            ? Role::with('users')->whereIn('id', $roleIds)->get()->keyBy('id')
            : collect();

        $adminIds = collect();
        $recipientSeverities = [];
        $ranks = ['info' => 0, 'warning' => 1, 'critical' => 2];

        foreach ($rules as $rule) {
            $targets = collect();
            if ($rule->route_to_admin_id !== null) {
                $targets->push($rule->route_to_admin_id);
            } elseif ($rule->route_to_role_id !== null && $roleUsers->has($rule->route_to_role_id)) {
                $targets = $roleUsers->get($rule->route_to_role_id)->users->pluck('id');
            }

            foreach ($targets as $adminId) {
                $adminIds->push($adminId);
                $currentSeverity = $recipientSeverities[$adminId] ?? $severity;
                $recipientSeverities[$adminId] = $isApprovalSla && $ranks[$rule->severity->value] > $ranks[$currentSeverity->value]
                    ? $rule->severity
                    : $currentSeverity;
            }
        }

        $adminIds = DB::table('users')
            ->whereIn('users.id', $adminIds->unique())
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', 'App\\Modules\\Identity\\Domain\\Models\\User')
                    ->where('roles.guard_name', 'web')
                    ->whereIn('roles.name', ['admin', 'booking_manager', 'super_admin', 'panel_user']);
            })
            ->pluck('users.id');

        if ($adminIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($adminIds, $sourceType, $sourceId, $title, $body, $recipientSeverities, $eventKey, $isApprovalSla) {
            foreach ($adminIds as $adminId) {
                $severity = $recipientSeverities[$adminId];
                AdminInboxItem::query()->insertOrIgnore([[
                    'public_id' => Str::ulid()->toBase32(),
                    'admin_id' => $adminId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'severity' => $severity->value,
                    'title' => json_encode($title),
                    'body' => json_encode($body),
                    'status' => AdminInboxStatus::Unread->value,
                    'snoozed_until' => null,
                    'assigned_to_admin_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]]);

                // Escalate the existing source alert once; repeated SLA checks preserve its read/resolved state.
                if ($isApprovalSla) {
                    $lowerSeverities = match ($severity) {
                        AdminInboxSeverity::Critical => ['info', 'warning'],
                        AdminInboxSeverity::Warning => ['info'],
                        AdminInboxSeverity::Info => [],
                    };
                    AdminInboxItem::query()
                        ->where('admin_id', $adminId)
                        ->where('source_type', $sourceType)
                        ->where('source_id', $sourceId)
                        ->where(function ($query) use ($lowerSeverities, $eventKey, $severity): void {
                            $query->whereIn('severity', $lowerSeverities);
                            // A critical 24h rule still advances to the 48h condition once.
                            if ($eventKey === 'vendor.approval.sla_48h') {
                                $query->orWhere(fn ($stage) => $stage
                                    ->where('severity', $severity->value)
                                    ->where('title->en', 'like', '[SLA 24H]%'));
                            }
                        })
                        ->update([
                            'severity' => $severity->value,
                            'title' => json_encode($title),
                            'body' => json_encode($body),
                            'status' => AdminInboxStatus::Unread->value,
                            'snoozed_until' => null,
                            'updated_at' => now(),
                        ]);
                }
            }
        });
    }
}
