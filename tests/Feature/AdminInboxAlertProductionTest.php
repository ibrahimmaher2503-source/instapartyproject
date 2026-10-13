<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Support\Application\Actions\SubmitSupportTicketAction;
use App\Modules\Support\Application\DTOs\SubmitSupportTicketDTO;
use App\Modules\Support\Domain\Events\SupportTicketCreated;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

it('escalates the existing vendor alert once at each SLA threshold', function (AdminInboxStatus $status): void {
    $this->freezeTime();
    $admin = User::factory()->asAdmin()->create();
    $vendor = VendorProfile::factory()->pending()->create(['created_at' => now()->subHours(25)]);
    foreach (['vendor.approval.sla_24h' => AdminInboxSeverity::Warning, 'vendor.approval.sla_48h' => AdminInboxSeverity::Critical] as $eventKey => $severity) {
        AdminInboxRoutingRule::factory()->forAdmin($admin->id)->create(compact('severity') + ['event_key' => $eventKey]);
    }
    $item = AdminInboxItem::factory()->create([
        'admin_id' => $admin->id,
        'source_id' => $vendor->id,
        'severity' => AdminInboxSeverity::Info,
        'status' => $status,
        'assigned_to_admin_id' => $admin->id,
    ]);

    $this->artisan('communication:check-vendor-approval-sla')->assertSuccessful();
    expect($item->refresh()->severity)->toBe(AdminInboxSeverity::Warning)
        ->and($item->status)->toBe(AdminInboxStatus::Unread)
        ->and($item->assigned_to_admin_id)->toBe($admin->id)
        ->and($item->getTranslation('title', 'en'))->toContain('[SLA 24H]')
        ->and($item->getTranslation('title', 'ar'))->toContain('24');

    $item->update(['status' => $status]);
    $this->artisan('communication:check-vendor-approval-sla')->assertSuccessful();
    expect($item->refresh()->status)->toBe($status);

    $this->travel(24)->hours();
    $this->artisan('communication:check-vendor-approval-sla')->assertSuccessful();
    expect($item->refresh()->severity)->toBe(AdminInboxSeverity::Critical)
        ->and($item->status)->toBe(AdminInboxStatus::Unread)
        ->and($item->getTranslation('title', 'en'))->toContain('[SLA 48H]');

    $item->update(['status' => $status]);
    $this->artisan('communication:check-vendor-approval-sla')->assertSuccessful();
    expect($item->refresh()->status)->toBe($status)
        ->and(AdminInboxItem::query()->count())->toBe(1);
})->with([AdminInboxStatus::Read, AdminInboxStatus::Resolved]);

it('honors critical 24h routing and advances the same priority alert to 48h once', function (): void {
    $this->freezeTime();
    $admin = User::factory()->asAdmin()->create();
    $vendor = VendorProfile::factory()->pending()->create(['created_at' => now()->subHours(25)]);
    foreach (['vendor.approval.sla_24h', 'vendor.approval.sla_48h'] as $eventKey) {
        AdminInboxRoutingRule::factory()->forAdmin($admin->id)->create([
            'event_key' => $eventKey, 'severity' => AdminInboxSeverity::Critical,
        ]);
    }
    AdminInboxRoutingRule::factory()->forRole(Role::findByName('admin')->id)->create([
        'event_key' => 'vendor.approval.sla_24h', 'severity' => AdminInboxSeverity::Warning,
    ]);
    $this->artisan('communication:check-vendor-approval-sla')->assertSuccessful();
    $item = AdminInboxItem::query()->sole();
    expect($item->severity)->toBe(AdminInboxSeverity::Critical)
        ->and($item->getTranslation('title', 'en'))->toContain('[SLA 24H]');
    $item->update(['status' => AdminInboxStatus::Resolved]);
    $this->artisan('communication:check-vendor-approval-sla')->assertSuccessful();
    expect($item->refresh()->status)->toBe(AdminInboxStatus::Resolved);
    $this->travel(24)->hours();
    $this->artisan('communication:check-vendor-approval-sla')->assertSuccessful();
    expect($item->refresh()->getTranslation('title', 'en'))->toContain('[SLA 48H]')
        ->and($item->status)->toBe(AdminInboxStatus::Unread);
    $item->update(['status' => AdminInboxStatus::Read]);
    $this->artisan('communication:check-vendor-approval-sla')->assertSuccessful();
    expect($item->refresh()->status)->toBe(AdminInboxStatus::Read);
});

it('alerts only for pending non deleted vendors that have reached a threshold', function (): void {
    $this->freezeTime();
    $admin = User::factory()->asAdmin()->create();
    foreach (['vendor.approval.sla_24h' => AdminInboxSeverity::Warning, 'vendor.approval.sla_48h' => AdminInboxSeverity::Critical] as $eventKey => $severity) {
        AdminInboxRoutingRule::factory()->forAdmin($admin->id)->create(compact('severity') + ['event_key' => $eventKey]);
    }
    $warning = VendorProfile::factory()->pending()->create(['created_at' => now()->subHours(24)]);
    $critical = VendorProfile::factory()->pending()->create(['created_at' => now()->subHours(48)]);
    VendorProfile::factory()->pending()->create(['created_at' => now()->subHours(23)]);
    VendorProfile::factory()->approved()->create(['created_at' => now()->subHours(72)]);
    VendorProfile::factory()->pending()->create(['created_at' => now()->subHours(72)])->delete();

    $this->artisan('communication:check-vendor-approval-sla')->assertSuccessful();
    expect(AdminInboxItem::query()->count())->toBe(2);
    $this->assertDatabaseHas('admin_inbox_items', ['source_id' => $warning->id, 'severity' => 'warning']);
    $this->assertDatabaseHas('admin_inbox_items', ['source_id' => $critical->id, 'severity' => 'critical']);
});

it('routes committed support submissions once and does not route a rollback', function (): void {
    $admin = User::factory()->asAdmin()->create();
    AdminInboxRoutingRule::factory()->forRole(Role::findByName('admin')->id)->create([
        'event_key' => 'support.ticket.created', 'severity' => AdminInboxSeverity::Info,
    ]);
    $dto = new SubmitSupportTicketDTO('Help with an event', 'Please review my request.', null, null, 'guest@example.com');
    $ticket = app(SubmitSupportTicketAction::class)->execute($dto);
    expect(app(SubmitSupportTicketAction::class)->execute($dto)->id)->toBe($ticket->id);
    $item = AdminInboxItem::query()->sole();
    expect($item->admin_id)->toBe($admin->id)
        ->and($item->source_type)->toBe('support_ticket')
        ->and($item->source_id)->toBe($ticket->id)
        ->and($item->getTranslation('title', 'en'))->toContain('TK-')
        ->and($item->getTranslation('title', 'ar'))->toContain('TK-');
    $item->update(['status' => AdminInboxStatus::Read]);
    event(new SupportTicketCreated($ticket));
    expect($item->refresh()->status)->toBe(AdminInboxStatus::Read);

    DB::beginTransaction();
    app(SubmitSupportTicketAction::class)->execute(new SubmitSupportTicketDTO('Rollback', 'This must not alert.', null, null, 'guest@example.com'));
    DB::rollBack();
    expect(AdminInboxItem::query()->count())->toBe(1);
});

it('excludes inactive deleted and non admin recipients from configured routes', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $inactive = User::factory()->asAdmin()->create(['status' => 'suspended']);
    $deleted = User::factory()->asAdmin()->create();
    $deleted->delete();
    $customer = User::factory()->asCustomer()->create();
    AdminInboxRoutingRule::factory()->forRole(Role::findByName('admin')->id)->create([
        'event_key' => 'support.ticket.created', 'severity' => AdminInboxSeverity::Info,
    ]);
    foreach ([$inactive, $deleted, $customer] as $user) {
        AdminInboxRoutingRule::factory()->forAdmin($user->id)->create([
            'event_key' => 'support.ticket.created', 'severity' => AdminInboxSeverity::Info,
        ]);
    }
    app(RouteToAdminInboxAction::class)->execute('support.ticket.created', AdminInboxSeverity::Info, 'support_ticket', 100, ['en' => 'Ticket', 'ar' => 'تذكرة'], ['en' => 'Review ticket', 'ar' => 'مراجعة التذكرة']);
    expect(AdminInboxItem::query()->pluck('admin_id')->all())->toBe([$admin->id]);
});

it('adds support routing defaults without replacing an existing disabled rule', function (): void {
    $migration = require base_path('app/Modules/Communication/Database/Migrations/2026_10_13_100003_add_support_ticket_inbox_routing.php');
    AdminInboxRoutingRule::query()->where('event_key', 'support.ticket.created')->delete();
    $migration->up();
    expect(AdminInboxRoutingRule::query()->where('event_key', 'support.ticket.created')->count())->toBe(2);
    AdminInboxRoutingRule::query()->where('event_key', 'support.ticket.created')->update(['is_active' => false]);
    $migration->up();
    expect(AdminInboxRoutingRule::query()->where('event_key', 'support.ticket.created')->count())->toBe(2)
        ->and(AdminInboxRoutingRule::query()->where('event_key', 'support.ticket.created')->where('is_active', true)->exists())->toBeFalse();
});
