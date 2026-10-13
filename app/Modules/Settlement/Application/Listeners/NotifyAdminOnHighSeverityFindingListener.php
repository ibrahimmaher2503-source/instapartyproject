<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Listeners;

use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Events\ReconciliationFindingRaised;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Filament\Resources\ReconciliationFindingResource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class NotifyAdminOnHighSeverityFindingListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(ReconciliationFindingRaised $event): void
    {
        if ($event->severity !== ReconciliationFindingSeverity::High) {
            return;
        }

        DB::transaction(function () use ($event): void {
            // Serialize retries for this finding; committed dispatches must not be duplicated.
            $finding = ReconciliationFinding::query()->lockForUpdate()->findOrFail($event->findingId);
            if ($finding->resolved_at !== null) {
                return;
            }

            $admins = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_type', 'App\\Modules\\Identity\\Domain\\Models\\User')
                ->where('roles.guard_name', 'web')
                ->whereIn('roles.name', ['admin', 'super_admin'])
                ->where('users.status', 'active')
                ->whereNull('users.deleted_at')
                ->select('users.id', 'users.preferred_locale')
                ->distinct()
                ->get();

            foreach ($admins as $admin) {
                if (DB::table('notification_dispatches')
                    ->where('user_id', $admin->id)
                    ->where('channel', NotificationChannel::InApp->value)
                    ->where('reference_type', 'reconciliation_finding')
                    ->where('reference_id', $event->findingId)
                    ->where('context->finding_public_id', $event->findingPublicId)
                    ->exists()) {
                    continue;
                }

                $locale = in_array($admin->preferred_locale, ['en', 'ar'], true) ? $admin->preferred_locale : 'en';
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'settlement.reconciliation_high_finding',
                    channel: NotificationChannel::InApp,
                    audience: NotificationAudience::Admin,
                    eventCategory: EventCategory::System,
                    userId: (int) $admin->id,
                    context: [
                        'finding_public_id' => $event->findingPublicId,
                        'finding_type' => $event->findingType->value,
                        'severity' => $event->severity->value,
                        'notification_format' => 'filament',
                        'action_label' => __('settlement.nav.reconciliation_findings', [], $locale),
                        'action_url' => ReconciliationFindingResource::getUrl('index', ['tableSearch' => $event->findingPublicId], panel: 'admin'),
                    ],
                    referenceType: 'reconciliation_finding',
                    referenceId: $event->findingId,
                    directBody: __('settlement.findings.high_alert_body', [
                        'finding' => __('settlement.finding_type.'.$event->findingType->value, [], $locale),
                        'public_id' => $event->findingPublicId,
                    ], $locale),
                    directSubject: __('settlement.findings.high_alert_title', [], $locale),
                ));
            }
        });
    }
}
