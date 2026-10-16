<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use App\Modules\TrustSafety\Domain\Models\Report;
use App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages\ListReports;
use App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages\ViewReport;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function createReportAdmin(): User
{
    $admin = User::factory()->create();
    $admin->givePermissionTo(
        Permission::findOrCreate('view_any_report', 'web'),
        Permission::findOrCreate('view_report', 'web'),
    );
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $admin;
}

it('resolves all ReportReason and ReportStatus labels to human translations in EN and AR', function (): void {
    App::setLocale('en');

    foreach (ReportReason::cases() as $reason) {
        $label = $reason->label();
        expect($label)
            ->not->toContain('trust-safety::')
            ->not->toBeEmpty();
    }

    foreach (ReportStatus::cases() as $status) {
        $label = $status->label();
        expect($label)
            ->not->toContain('trust-safety::')
            ->not->toBeEmpty();
    }

    expect(ReportReason::InappropriateContent->label())->toBe('Inappropriate Content')
        ->and(ReportReason::FakeProfile->label())->toBe('Fake Profile')
        ->and(ReportReason::FraudulentActivity->label())->toBe('Fraudulent Activity')
        ->and(ReportStatus::Open->label())->toBe('Open')
        ->and(ReportStatus::UnderReview->label())->toBe('Under Review')
        ->and(ReportStatus::Resolved->label())->toBe('Resolved')
        ->and(ReportStatus::Dismissed->label())->toBe('Dismissed');

    App::setLocale('ar');

    expect(ReportReason::InappropriateContent->label())->toBe('محتوى غير لائق')
        ->and(ReportReason::FakeProfile->label())->toBe('ملف شخصي مزيف')
        ->and(ReportReason::FraudulentActivity->label())->toBe('نشاط احتيالي')
        ->and(ReportStatus::Open->label())->toBe('مفتوح')
        ->and(ReportStatus::UnderReview->label())->toBe('قيد المراجعة')
        ->and(ReportStatus::Resolved->label())->toBe('محلول')
        ->and(ReportStatus::Dismissed->label())->toBe('مرفوض');

    App::setLocale('en');
});

it('renders translated human labels for reason, status and target type in Admin Reports queue', function (): void {
    App::setLocale('en');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = createReportAdmin();
    $this->actingAs($admin);

    $reporter = User::factory()->create();
    $report = Report::query()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => 'vendor_profile',
        'reportable_id' => 1,
        'reason' => ReportReason::FraudulentActivity->value,
        'status' => ReportStatus::Open->value,
    ]);

    Livewire::test(ListReports::class)
        ->assertSuccessful()
        ->assertSee('Fraudulent Activity')
        ->assertSee('Open')
        ->assertSee('Vendor Profile')
        ->assertDontSee('trust-safety::reasons.fraudulent_activity')
        ->assertDontSee('trust-safety::statuses.open');
});

it('renders Arabic human labels in Admin Reports queue when in Arabic locale', function (): void {
    App::setLocale('ar');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = createReportAdmin();
    $this->actingAs($admin);

    $reporter = User::factory()->create();
    $report = Report::query()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => 'vendor_profile',
        'reportable_id' => 1,
        'reason' => ReportReason::InappropriateContent->value,
        'status' => ReportStatus::UnderReview->value,
    ]);

    Livewire::test(ListReports::class)
        ->assertSuccessful()
        ->assertSee('محتوى غير لائق')
        ->assertSee('قيد المراجعة')
        ->assertSee('ملف التاجر')
        ->assertDontSee('trust-safety::reasons.inappropriate_content')
        ->assertDontSee('trust-safety::statuses.under_review');

    App::setLocale('en');
});

it('renders formatted and translated fields in ViewReport infolist', function (): void {
    App::setLocale('en');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = createReportAdmin();
    $this->actingAs($admin);

    $reporter = User::factory()->create();
    $report = Report::query()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => 'vendor_profile',
        'reportable_id' => 1,
        'reason' => ReportReason::Harassment->value,
        'status' => ReportStatus::Resolved->value,
    ]);

    Livewire::test(ViewReport::class, ['record' => $report->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Harassment')
        ->assertSee('Resolved')
        ->assertSee('Vendor Profile')
        ->assertDontSee('trust-safety::reasons.harassment')
        ->assertDontSee('trust-safety::statuses.resolved');
});
