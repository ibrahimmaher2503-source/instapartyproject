<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\ApproveVendorDocumentAction;
use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\UploadVendorDocumentAction;
use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Filament\Vendor\Pages\VendorBusinessHoursPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorCoverageAreasPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorPhoneVerificationPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
    Queue::fake();
});

it('completes one vendor onboarding journey through the existing gates', function (): void {
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    SubscriptionPlan::factory()->free()->create();

    $registration = $this->postJson('/api/v1/register/vendor', [
        'name' => 'Full Journey Vendor',
        'email' => 'full-journey@example.test',
        'phone_e164' => '+201000008888',
        'password' => 'SafePassword123!',
        'password_confirmation' => 'SafePassword123!',
        'business_name' => ['en' => 'Full Journey Events', 'ar' => 'فعاليات الرحلة الكاملة'],
        'business_type' => 'individual',
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
        'preferred_locale' => 'en',
    ]);

    $registration->assertCreated()
        ->assertJsonPath('meta.registration_successful', true)
        ->assertJsonPath('meta.verification_pending', true);

    $user = User::query()->where('email', 'full-journey@example.test')->firstOrFail();
    $profile = VendorProfile::query()->where('user_id', $user->getKey())->firstOrFail();

    expect($user->public_id)->not->toBeEmpty()
        ->and($profile->public_id)->not->toBeEmpty()
        ->and($profile->approval_status->getValue())->toBe('pending');

    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $emailVerificationUrl = Filament::getPanel('vendor')->getVerifyEmailUrl($user);

    $this->actingAs($user, 'vendor')
        ->get($emailVerificationUrl)
        ->assertRedirect();

    $user = $user->fresh();
    expect($user->email_verified_at)->not->toBeNull();

    $gateway = new class implements OtpGatewayInterface
    {
        public int $sendCalls = 0;

        public function send(string $phoneE164, string $code): void
        {
            $this->sendCalls++;
        }

        public function verify(string $phoneE164, string $code): bool
        {
            return $code === '123456';
        }
    };

    $this->app->instance(OtpGatewayInterface::class, $gateway);
    $this->actingAs($user, 'vendor');

    Livewire::test(VendorPhoneVerificationPage::class)
        ->call('sendCode')
        ->fillForm(['code' => '123456'])
        ->call('verifyPhone')
        ->assertHasNoFormErrors();

    $user = $user->fresh();
    expect($gateway->sendCalls)->toBe(1)
        ->and($user->phone_verified_at)->not->toBeNull();

    Livewire::test(VendorProfilePage::class)
        ->fillForm([
            'business_name_en' => 'Full Journey Events',
            'business_name_ar' => 'فعاليات الرحلة الكاملة',
            'address_line_en' => '12 Market Street',
            'address_line_ar' => '١٢ شارع السوق',
            'national_id' => '29801011234567',
            'primary_governorate_id' => $governorate->id,
            'primary_city_id' => $city->id,
            'bank_name' => 'Example Bank',
            'bank_account_holder' => 'Full Journey Vendor',
            'bank_iban' => 'EG380019000500000000263180002',
            'bank_swift' => 'EXAMPLEGXXX',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $profile = $profile->fresh();
    expect($profile->getTranslation('business_name', 'en'))->toBe('Full Journey Events')
        ->and($profile->getTranslation('business_name', 'ar'))->toBe('فعاليات الرحلة الكاملة')
        ->and($profile->national_id)->toBe('29801011234567')
        ->and($profile->getRawOriginal('bank_iban'))->toBe('EG380019000500000000263180002');

    Livewire::test(VendorDocumentsPage::class)
        ->assertActionExists('upload');

    $documentAction = app(UploadVendorDocumentAction::class);
    $nationalId = $documentAction->execute(
        $profile,
        UploadedFile::fake()->create('national-id.pdf', 40, 'application/pdf'),
        DocumentType::NationalId,
    );
    $ibanProof = $documentAction->execute(
        $profile,
        UploadedFile::fake()->create('iban-proof.pdf', 40, 'application/pdf'),
        DocumentType::IbanProof,
    );

    expect($nationalId->public_id)->not->toBeEmpty()
        ->and($ibanProof->public_id)->not->toBeEmpty()
        ->and($nationalId->status)->toBe(DocumentStatus::Pending)
        ->and($ibanProof->status)->toBe(DocumentStatus::Pending);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['auth.defaults.guard' => 'web']);
    $reviewer = User::factory()->create();
    $reviewPermission = Permission::findOrCreate('review_vendor_documents', 'web');
    $reviewer->givePermissionTo($reviewPermission);
    expect($reviewer->fresh()->can('review_vendor_documents'))->toBeTrue();

    $nationalId = app(ApproveVendorDocumentAction::class)->execute($nationalId, $reviewer);
    $ibanProof = app(ApproveVendorDocumentAction::class)->execute($ibanProof, $reviewer);

    expect($nationalId->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and($ibanProof->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and($nationalId->fresh()->reviewed_by)->toBe($reviewer->getKey())
        ->and($ibanProof->fresh()->reviewed_by)->toBe($reviewer->getKey());

    $reviewer->givePermissionTo(Permission::findOrCreate('approve_vendor_profile', 'web'));

    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($user, 'vendor');

    Livewire::test(VendorBusinessHoursPage::class)
        ->fillForm([
            'day_0_open' => true,
            'day_0_opens_at' => '09:00',
            'day_0_closes_at' => '22:00',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($profile->fresh()->businessHours()->exists())->toBeTrue();

    Livewire::test(VendorCoverageAreasPage::class)
        ->callAction('addArea', data: [
            'city_id' => $city->id,
            'delivery_fee' => 0,
            'min_order' => 0,
        ])
        ->assertHasNoActionErrors();

    $profile = $profile->fresh();
    expect($profile->coverageAreas()->exists())->toBeTrue();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['auth.defaults.guard' => 'web']);
    $eligibility = app(VendorApprovalEligibilityService::class)->evaluate($profile);

    expect($eligibility->eligible)->toBeTrue()
        ->and($eligibility->checks)->toMatchArray([
            'identity' => true,
            'contact' => true,
            'profile' => true,
            'geography' => true,
            'banking' => true,
            'hours' => true,
            'coverage' => true,
            'documents' => true,
        ]);

    $approvedProfile = app(ApproveVendorProfileAction::class)->execute($profile, $reviewer);

    expect($approvedProfile->approval_status)->toBeInstanceOf(ApprovedState::class)
        ->and($approvedProfile->approved_by)->toBe($reviewer->getKey())
        ->and(VendorDocument::query()->whereKey($nationalId)->firstOrFail()->status)->toBe(DocumentStatus::Approved)
        ->and(VendorDocument::query()->whereKey($ibanProof)->firstOrFail()->status)->toBe(DocumentStatus::Approved);
});
