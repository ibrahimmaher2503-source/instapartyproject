<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\CreateRentalServiceDTO;
use App\Modules\Catalog\Application\Services\CategoryFieldValidationService;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\RentalServiceCreated;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionPolicyContract;
use App\Modules\Subscriptions\Domain\Exceptions\SubscriptionLimitReachedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateRentalServiceAction
{
    public function __construct(private readonly SubscriptionPolicyContract $subscriptionPolicy) {}

    public function execute(CreateRentalServiceDTO $dto): Service
    {
        $decision = $this->subscriptionPolicy->canCreateService($dto->vendorProfileId, ProductType::Rental);
        if (! $decision->allowed) {
            throw new SubscriptionLimitReachedException(
                $decision->featureKey,
                $decision->currentCount,
                $decision->limit,
                $decision->unblockingPlanCode,
                $decision->currentPlanCode,
            );
        }

        return DB::transaction(function () use ($dto): Service {
            $values = app(CategoryFieldValidationService::class)->validateValues(
                $dto->categoryId,
                ProductType::Rental,
                $dto->categoryFieldValues ?? [],
                'category_field_values.',
            );
            $service = Service::create([
                'public_id' => Str::ulid()->toBase32(),
                'vendor_profile_id' => $dto->vendorProfileId,
                'category_id' => $dto->categoryId,
                'product_type' => ProductType::Rental,
                'name' => $dto->name,
                'short_description' => $dto->shortDescription,
                ...($dto->longDescription === null ? [] : ['long_description' => $dto->longDescription]),
                'slug' => Str::slug($dto->name['en']).'-'.Str::lower(Str::random(6)),
                'status' => ServiceStatus::Draft->value,
                'base_price_minor' => $dto->basePriceMinor,
                'base_price_currency' => 'EGP',
                'category_field_values' => $values,
            ]);

            ServiceRentalDetail::create([
                'service_id' => $service->id,
                'requires_electricity' => $dto->requiresElectricity,
                'requires_outdoor_space' => $dto->requiresOutdoorSpace,
                'default_rental_duration_hours' => $dto->defaultRentalDurationHours,
                'setup_time_minutes' => $dto->setupTimeMinutes ?? 0,
                'teardown_time_minutes' => $dto->teardownTimeMinutes ?? 0,
                'security_deposit_minor' => $dto->securityDepositMinor ?? 0,
                'security_deposit_currency' => 'EGP',
                'minimum_space_sqm' => $dto->minimumSpaceSqm,
                'length_m' => $dto->lengthM,
                'width_m' => $dto->widthM,
                'height_m' => $dto->heightM,
            ]);

            $loaded = $service->load('rentalDetail');
            DB::afterCommit(fn () => event(new RentalServiceCreated($loaded)));

            return $loaded;
        });
    }
}
