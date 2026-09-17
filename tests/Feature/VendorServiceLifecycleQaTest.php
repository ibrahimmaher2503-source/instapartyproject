<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\CreateDigitalServiceAction;
use App\Modules\Catalog\Application\Actions\CreateRentalServiceAction;
use App\Modules\Catalog\Application\Actions\CreateSaleServiceAction;
use App\Modules\Catalog\Application\Actions\PublishServiceAction;
use App\Modules\Catalog\Application\Actions\RejectServiceAction;
use App\Modules\Catalog\Application\Actions\RequestDigitalServiceChangesAction;
use App\Modules\Catalog\Application\Actions\SubmitServiceForReviewAction;
use App\Modules\Catalog\Application\DTOs\CreateDigitalServiceDTO;
use App\Modules\Catalog\Application\DTOs\CreateRentalServiceDTO;
use App\Modules\Catalog\Application\DTOs\CreateSaleServiceDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Events\DigitalServiceCreated;
use App\Modules\Catalog\Domain\Events\RentalServiceCreated;
use App\Modules\Catalog\Domain\Events\SaleServiceCreated;
use App\Modules\Catalog\Domain\Events\ServicePublished;
use App\Modules\Catalog\Domain\Events\ServiceRejected;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ChangesRequestedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PendingReviewState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\RejectedState;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Events\ChangeRequestCreated;
use App\Modules\Subscriptions\Application\DTOs\PolicyDecisionDto;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionPolicyContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\VendorLifecycleQaEnvironment;

beforeEach(function (): void {
    VendorLifecycleQaEnvironment::boot();
    Event::fake([
        DigitalServiceCreated::class,
        RentalServiceCreated::class,
        SaleServiceCreated::class,
        ServicePublished::class,
        ServiceRejected::class,
        ChangeRequestCreated::class,
    ]);
    Role::findOrCreate('vendor', 'web');

    $policy = Mockery::mock(SubscriptionPolicyContract::class);
    $policy->shouldReceive('canCreateService')->andReturn(PolicyDecisionDto::allow());
    app()->instance(SubscriptionPolicyContract::class, $policy);
});

afterEach(function (): void {
    VendorLifecycleQaEnvironment::resetClock();
});

it('creates and submits all three service types then applies each moderation outcome', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $admin = User::factory()->create();
    $outsider = User::factory()->create();

    foreach (ProductType::cases() as $type) {
        VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
        $permission = Permission::findOrCreate("publish_{$type->value}_service", 'web');
        $admin->givePermissionTo($permission);
    }
    foreach (['reject_service', 'request_service_edits'] as $permission) {
        $permissionModel = Permission::findOrCreate($permission, 'web');
        $admin->givePermissionTo($permissionModel);
    }

    $services = createQaServices($vendor);
    foreach ($services as $service) {
        $submitted = app(SubmitServiceForReviewAction::class)->execute($service, $vendor);
        expect($submitted->status)->toBeInstanceOf(PendingReviewState::class);
    }

    $this->actingAs($outsider);
    expect(fn () => app(PublishServiceAction::class)->execute($services['rental'], $outsider))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($admin);
    $services['rental']->addMedia(UploadedFile::fake()->image('qa.jpg'))->toMediaCollection('gallery');
    expect(app(PublishServiceAction::class)->execute($services['rental'], $admin)->status)
        ->toBeInstanceOf(PublishedState::class);

    expect(app(RejectServiceAction::class)->execute(
        $services['sale'],
        ['en' => 'QA rejection reason', 'ar' => 'سبب رفض اختباري'],
        $admin,
    )->status)->toBeInstanceOf(RejectedState::class);

    app(RequestDigitalServiceChangesAction::class)->execute(
        $services['digital'],
        [[
            'field_path' => 'short_description',
            'requested_change_en' => 'Clarify delivery details.',
            'requested_change_ar' => 'وضّح تفاصيل التسليم.',
        ]],
        $admin,
        'qa-service-changes-001',
    );

    expect($services['digital']->refresh()->status)->toBeInstanceOf(ChangesRequestedState::class);
});

/** @return array{rental: Service, sale: Service, digital: Service} */
function createQaServices(VendorProfile $vendor): array
{
    $category = Category::factory()->create([
        'allowed_product_types' => array_map(static fn (ProductType $type): string => $type->value, ProductType::cases()),
    ]);
    $common = [
        'vendorProfileId' => $vendor->id,
        'categoryId' => $category->id,
        'name' => ['en' => 'QA lifecycle service', 'ar' => 'خدمة دورة اختبار'],
        'shortDescription' => ['en' => 'Created by isolated QA.', 'ar' => 'تم إنشاؤها في اختبار معزول.'],
        'basePriceMinor' => 10000,
    ];

    return [
        'rental' => app(CreateRentalServiceAction::class)->execute(new CreateRentalServiceDTO(
            ...$common,
            requiresElectricity: false,
            requiresOutdoorSpace: false,
            defaultRentalDurationHours: 4,
            setupTimeMinutes: 30,
            teardownTimeMinutes: 30,
            securityDepositMinor: 0,
            minimumSpaceSqm: null,
        )),
        'sale' => app(CreateSaleServiceAction::class)->execute(new CreateSaleServiceDTO(
            ...$common,
            isPerishable: false,
            isMadeToOrder: true,
            leadTimeHours: 24,
            stockQuantity: 10,
            customizationFields: null,
        )),
        'digital' => app(CreateDigitalServiceAction::class)->execute(new CreateDigitalServiceDTO(
            ...$common,
            deliveryMethod: 'link',
            hasExpiry: false,
            expiryDaysAfterPurchase: null,
            isRefundableAfterDelivery: false,
            redemptionUrlTemplate: null,
        )),
    ];
}
