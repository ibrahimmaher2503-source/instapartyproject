<?php

declare(strict_types=1);

use App\Modules\Catalog\Database\Factories\ExcelImportFactory;
use App\Modules\Communication\Database\Factories\CampaignFactory;
use App\Modules\Communication\Database\Factories\ChatMessageLogFactory;
use App\Modules\Communication\Database\Factories\ChatModerationFlagFactory;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Http\Resources\ChatModerationFlagResource;
use App\Modules\Communication\Infrastructure\Services\MaskModerationPattern;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Database\Factories\PaymentFactory;
use App\Modules\Payments\Database\Factories\RefundFactory;
use App\Modules\Payments\Domain\Models\IdempotencyKey;
use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use App\Modules\TrustSafety\Domain\Models\Report;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('masks moderation patterns according to their type', function (): void {
    expect(MaskModerationPattern::run('+201001234567', ChatFlagType::Phone))
        ->toBe('+**********67')
        ->and(MaskModerationPattern::run('person@example.test', ChatFlagType::Email))
        ->toBe('p***@example.test')
        ->and(MaskModerationPattern::run('https://external.example/path', ChatFlagType::ExternalLink))
        ->toBe('[redacted external link]');

    $flag = ChatModerationFlagFactory::new()->ofType(ChatFlagType::Phone)->create();
    $data = (new ChatModerationFlagResource($flag))->toArray(Request::create('/'));

    expect($data['matched_pattern'])->toBe('+**********67');

    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('chat_moderation.view', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/chat-moderation-flags')
        ->assertOk()
        ->assertDontSee('+201001234567')
        ->assertSee('+**********67');
});

it('renders communication and trust safety detail pages', function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach ([
        'chat_moderation.view',
        'view_any_report',
        'view_report',
        'view_any_campaign',
        'view_campaign',
        'view_any_chat::moderation::flag',
        'view_chat::moderation::flag',
        'view_any_vendor::profile',
        'view_vendor::profile',
    ] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $reporter = User::factory()->create();
    $report = Report::query()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => User::class,
        'reportable_id' => $reporter->id,
        'reason' => ReportReason::Harassment,
        'details' => 'QA report detail',
        'status' => ReportStatus::Open,
    ]);
    $campaign = CampaignFactory::new()->create();
    $flag = ChatModerationFlagFactory::new()->create();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/reports/'.$report->public_id)->assertOk();
    $this->get('/admin/campaigns/'.$campaign->public_id)->assertOk();
    $this->get('/admin/campaigns/'.$campaign->getKey())->assertNotFound();
    $this->get('/admin/chat-moderation-flags/'.$flag->getKey())->assertOk();
    $this->get('/admin/chat-threads/'.$flag->thread->getKey())->assertOk();
});

it('renders message log and spreadsheet import detail pages', function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['chat_moderation.view', 'view_any_excel::import', 'view_excel::import'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $message = ChatMessageLogFactory::new()->create(['body' => 'QA message detail']);
    $import = ExcelImportFactory::new()->create();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/chat-message-logs/'.$message->getKey())->assertOk();
    $this->get('/admin/excel-imports/'.$import->public_id)->assertOk();
});

it('uses clear bilingual empty-state copy for the admin inbox', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_admin::inbox', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/admin-inboxes?lang=ar')
        ->assertOk()
        ->assertSee('لا توجد رسائل في صندوق وارد المشرف حاليًا');

    $this->get('/admin/admin-inboxes?lang=en')
        ->assertOk()
        ->assertSee('No inbox messages right now');
});

it('renders payment and refund detail pages for finance review', function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_refund', 'view_refund'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $payment = PaymentFactory::new()->captured()->create();
    $refund = RefundFactory::new()->failed()->create(['payment_id' => $payment->id]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/payments/'.$payment->public_id)->assertOk();
    $this->get('/admin/refunds/'.$refund->public_id)->assertOk();
});

it('does not expose full bank data or idempotency keys in admin lists', function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach ([
        'view_any_vendor::profile',
        'view_vendor::profile',
        'view_any_idempotency::key',
        'view_idempotency::key',
    ] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $vendor = VendorProfile::factory()->pending()->create([
        'bank_name' => 'QA Bank',
        'bank_account_holder' => 'QA Account Holder',
        'bank_iban' => 'EG380019000500000000263180002',
        'bank_swift_bic' => 'QABKEGCX',
    ]);
    $key = IdempotencyKey::factory()->create([
        'key' => 'qa-sensitive-idempotency-key-2026',
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/vendor-approval-queue/'.$vendor->public_id)
        ->assertOk()
        ->assertDontSee($vendor->bank_iban)
        ->assertSee('EG38••••002');

    $this->get('/admin/idempotency-keys')
        ->assertOk()
        ->assertDontSee($key->key)
        ->assertSee('qa-s…2026');
});
