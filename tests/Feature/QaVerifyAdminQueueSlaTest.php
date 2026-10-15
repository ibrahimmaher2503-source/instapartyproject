<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages\ListVendorApprovalQueue;
use App\Modules\Settlement\Database\Factories\WithdrawalFactory;
use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource\Pages\ListWithdrawalsQueue;
use App\Modules\Shared\Application\Services\AdminQueueSlaSettings;
use App\Modules\Shared\Domain\Enums\AdminQueue;
use App\Modules\Shared\Domain\Models\AppSetting;
use App\Modules\Shared\Filament\Pages\ManageSettings;
use App\Modules\Shared\Filament\Resources\AppSettingResource\Pages\EditAppSetting;
use App\Modules\Support\Domain\Enums\SupportTicketStatus;
use App\Modules\Support\Domain\Models\SupportTicket;
use App\Modules\Support\Filament\Resources\SupportTicketResource\Pages\ListSupportTickets;
use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use App\Modules\TrustSafety\Domain\Models\Report;
use App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages\ListReports;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function qaAdminQueueSlaActor(array $permissions): User
{
    $actor = User::factory()->superAdmin()->create();

    foreach ($permissions as $permission) {
        $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $actor->unsetRelation('permissions');

    return $actor;
}

it('defaults each configured admin queue SLA to 48 hours and honors valid independent values', function (): void {
    $settings = app(AdminQueueSlaSettings::class);

    foreach (AdminQueue::cases() as $queue) {
        AppSetting::query()->updateOrCreate(
            ['key' => $queue->settingKey()],
            ['value' => 48, 'description' => 'Queue response target in hours'],
        );
    }

    foreach (AdminQueue::cases() as $queue) {
        expect($settings->hours($queue))->toBe(48);
    }

    AppSetting::query()->where('key', AdminQueue::VendorApproval->settingKey())->update(['value' => 24]);
    AppSetting::query()->where('key', AdminQueue::Withdrawal->settingKey())->update(['value' => 72]);

    expect($settings->hours(AdminQueue::VendorApproval))->toBe(24)
        ->and($settings->hours(AdminQueue::Withdrawal))->toBe(72)
        ->and($settings->hours(AdminQueue::Report))->toBe(48)
        ->and($settings->hours(AdminQueue::Support))->toBe(48);

    AppSetting::query()->where('key', AdminQueue::VendorApproval->settingKey())->update(['value' => 1]);
    AppSetting::query()->where('key', AdminQueue::Withdrawal->settingKey())->update(['value' => 720]);

    expect($settings->hours(AdminQueue::VendorApproval))->toBe(1)
        ->and($settings->hours(AdminQueue::Withdrawal))->toBe(720);
});

it('uses a closed queue allowlist and defaults invalid persisted SLA values safely', function (mixed $invalid): void {
    $settings = app(AdminQueueSlaSettings::class);
    $queue = AdminQueue::Support;

    AppSetting::query()->updateOrCreate(
        ['key' => $queue->settingKey()],
        ['value' => $invalid, 'description' => 'Queue response target in hours'],
    );

    expect($settings->hours($queue))->toBe(48);
})->with([0, -1, 721, '48.5', 'invalid']);

it('marks a queue overdue exactly at its independently configured deadline', function (): void {
    $settings = app(AdminQueueSlaSettings::class);
    $startedAt = CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC');
    foreach (AdminQueue::cases() as $queue) {
        $deadline = $startedAt->addHours(48);
        AppSetting::query()->updateOrCreate(
            ['key' => $queue->settingKey()],
            ['value' => 48, 'description' => 'Queue response target in hours'],
        );

        expect($settings->deadlineFor($queue, $startedAt)->equalTo($deadline))->toBeTrue()
            ->and($settings->isOverdue($queue, $startedAt, $deadline->subSecond()))->toBeFalse()
            ->and($settings->isOverdue($queue, $startedAt, $deadline))->toBeTrue()
            ->and($settings->isOverdue($queue, $startedAt, $deadline->addSecond()))->toBeTrue();
    }
});

it('shows age and SLA fields on all four work queues and sorts active work before terminal records', function (): void {
    $this->freezeTime();
    app()->setLocale('en');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = qaAdminQueueSlaActor([
        'view_any_vendor::profile', 'view_vendor::profile',
        'view_any_withdrawals::queue', 'view_withdrawals::queue',
        'view_any_report', 'view_report',
        'view_any_support::ticket', 'view_support::ticket',
    ]);
    $actor->update(['timezone' => 'Asia/Dubai']);
    $this->actingAs($actor);
    foreach ([
        [AdminQueue::VendorApproval, 48],
        [AdminQueue::Withdrawal, 24],
        [AdminQueue::Report, 72],
        [AdminQueue::Support, 1],
    ] as [$queue, $hours]) {
        AppSetting::query()->where('key', $queue->settingKey())->update(['value' => $hours]);
    }

    $oldVendor = VendorProfile::factory()->pending()->create([
        'created_at' => now('UTC')->subHours(48),
        'updated_at' => now('UTC'),
    ]);
    $newVendor = VendorProfile::factory()->changesRequested()->create([
        'created_at' => now('UTC')->subHours(24),
        'updated_at' => now('UTC'),
    ]);
    $terminalVendor = VendorProfile::factory()->approved()->create([
        'created_at' => now('UTC')->subDays(10),
        'updated_at' => now('UTC')->subDays(10),
    ]);
    $vendorPage = Livewire::test(ListVendorApprovalQueue::class)
        ->assertTableColumnExists('queue_age')
        ->assertTableColumnExists('sla_deadline')
        ->assertTableColumnExists('sla_status')
        ->assertSeeText(__('shared::shared.admin_queue_sla.overdue'))
        ->assertSeeText(__('shared::shared.admin_queue_sla.within_sla'))
        ->assertSeeText('Asia/Dubai')
        ->assertSeeText($oldVendor->created_at->copy()->addHours(48)->timezone('Asia/Dubai')->format('d M Y H:i').' Asia/Dubai')
        ->assertCanSeeTableRecords([$oldVendor, $newVendor])
        ->assertCanNotSeeTableRecords([$terminalVendor]);
    expect($vendorPage->instance()->getFilteredSortedTableQuery()->pluck('id')->all())
        ->toBe([$oldVendor->id, $newVendor->id]);

    $oldWithdrawal = WithdrawalFactory::new()->pending()->create([
        'requested_at' => now('UTC')->subHours(48),
        'created_at' => now('UTC'),
    ]);
    $newWithdrawal = WithdrawalFactory::new()->approved()->create([
        'requested_at' => now('UTC')->subHours(12),
        'created_at' => now('UTC')->subHours(12),
    ]);
    $terminalWithdrawal = WithdrawalFactory::new()->paid()->create([
        'requested_at' => now('UTC')->subDays(10),
        'created_at' => now('UTC')->subDays(10),
    ]);
    $withdrawalPage = Livewire::test(ListWithdrawalsQueue::class)
        ->assertTableColumnExists('queue_age')
        ->assertTableColumnExists('sla_deadline')
        ->assertTableColumnExists('sla_status')
        ->assertSeeText(__('shared::shared.admin_queue_sla.overdue'))
        ->assertSeeText(__('shared::shared.admin_queue_sla.within_sla'))
        ->assertSeeText('Asia/Dubai')
        ->assertSeeText($oldWithdrawal->requested_at->copy()->addHours(24)->timezone('Asia/Dubai')->format('d M Y H:i').' Asia/Dubai')
        ->assertCanSeeTableRecords([$oldWithdrawal, $newWithdrawal])
        ->assertCanNotSeeTableRecords([$terminalWithdrawal]);
    expect($withdrawalPage->instance()->getFilteredSortedTableQuery()->pluck('id')->all())
        ->toBe([$oldWithdrawal->id, $newWithdrawal->id]);

    $reporter = User::factory()->create();
    $makeReport = static function (ReportStatus $status, int $ageHours) use ($reporter): Report {
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => User::class,
            'reportable_id' => $reporter->id,
            'reason' => ReportReason::Harassment->value,
            'details' => 'Queue SLA regression fixture.',
            'status' => $status->value,
        ]);
        $report->forceFill([
            'created_at' => now('UTC')->subHours($ageHours),
            'updated_at' => now('UTC'),
        ])->saveQuietly();

        return $report;
    };
    $oldReport = $makeReport(ReportStatus::Open, 72);
    $newReport = $makeReport(ReportStatus::UnderReview, 24);
    $terminalReport = $makeReport(ReportStatus::Resolved, 240);
    $reportPage = Livewire::test(ListReports::class)
        ->assertTableColumnExists('queue_age')
        ->assertTableColumnExists('sla_deadline')
        ->assertTableColumnExists('sla_status')
        ->assertSeeText(__('shared::shared.admin_queue_sla.overdue'))
        ->assertSeeText(__('shared::shared.admin_queue_sla.within_sla'))
        ->assertSeeText(__('shared::shared.admin_queue_sla.not_active'))
        ->assertSeeText($oldReport->created_at->copy()->addHours(72)->timezone('Asia/Dubai')->format('d M Y H:i').' Asia/Dubai')
        ->assertSeeText('Asia/Dubai');
    expect($reportPage->instance()->getFilteredSortedTableQuery()->pluck('id')->all())
        ->toBe([$oldReport->id, $newReport->id, $terminalReport->id]);

    $makeTicket = static function (SupportTicketStatus $status, int $ageHours): SupportTicket {
        $ticket = SupportTicket::query()->create([
            'subject' => 'Queue SLA '.$status->value,
            'body' => 'Queue SLA regression fixture.',
            'email' => 'queue-sla@example.test',
            'status' => $status,
        ]);
        $ticket->forceFill([
            'created_at' => now('UTC')->subHours($ageHours),
            'updated_at' => now('UTC'),
        ])->saveQuietly();

        return $ticket;
    };
    $oldTicket = $makeTicket(SupportTicketStatus::Open, 1);
    $newTicket = $makeTicket(SupportTicketStatus::InProgress, 0);
    $terminalTicket = $makeTicket(SupportTicketStatus::Closed, 240);
    $supportPage = Livewire::test(ListSupportTickets::class)
        ->assertTableColumnExists('queue_age')
        ->assertTableColumnExists('sla_deadline')
        ->assertTableColumnExists('sla_status')
        ->assertSeeText(__('shared::shared.admin_queue_sla.overdue'))
        ->assertSeeText(__('shared::shared.admin_queue_sla.within_sla'))
        ->assertSeeText(__('shared::shared.admin_queue_sla.not_active'))
        ->assertSeeText($oldTicket->created_at->copy()->addHour()->timezone('Asia/Dubai')->format('d M Y H:i').' Asia/Dubai')
        ->assertSeeText('Asia/Dubai');
    expect($supportPage->instance()->getFilteredSortedTableQuery()->pluck('id')->all())
        ->toBe([$oldTicket->id, $newTicket->id, $terminalTicket->id]);
});

it('lets admins independently edit each queue target and rejects values outside the supported range', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(qaAdminQueueSlaActor([
        'view_any_app::setting', 'view_app::setting', 'update_app::setting',
    ]));

    $page = Livewire::test(ManageSettings::class)
        ->assertSee(__('shared::shared.admin_queue_sla.vendor_approval'))
        ->assertSee(__('shared::shared.admin_queue_sla.withdrawal'))
        ->assertSee(__('shared::shared.admin_queue_sla.report'))
        ->assertSee(__('shared::shared.admin_queue_sla.support'))
        ->set('settings.'.AdminQueue::Report->settingKey(), '72')
        ->call('saveSettings');

    expect(AppSetting::query()->where('key', AdminQueue::Report->settingKey())->firstOrFail()->value)->toBe(72)
        ->and(AppSetting::query()->where('key', AdminQueue::Support->settingKey())->firstOrFail()->value)->toBe(48);

    $page->set('settings.'.AdminQueue::Support->settingKey(), '721')
        ->call('saveSettings')
        ->assertHasErrors(['settings.'.AdminQueue::Support->settingKey()]);

    expect(AppSetting::query()->where('key', AdminQueue::Support->settingKey())->firstOrFail()->value)->toBe(48);

    $supportSetting = AppSetting::query()->where('key', AdminQueue::Support->settingKey())->firstOrFail();
    Livewire::test(EditAppSetting::class, ['record' => $supportSetting->getRouteKey()])
        ->fillForm(['value_text' => '0'])
        ->call('save')
        ->assertHasFormErrors(['value_text']);

    expect($supportSetting->fresh()->value)->toBe(48);
});
