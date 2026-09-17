<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class VendorSearchQuery
{
    /**
     * @param  Builder<VendorProfile>  $query
     * @return Builder<VendorProfile>
     */
    public function apply(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $normalizedContact = preg_replace('/[^+\d@._-]+/u', '', Str::lower($search)) ?? '';
        $matchingTypes = collect(ProductType::cases())
            ->filter(function (ProductType $type) use ($search): bool {
                $needle = Str::lower($search);

                return str_contains(Str::lower($type->value), $needle)
                    || str_contains(Str::lower((string) __('identity.product_type.'.$type->value, locale: 'en')), $needle)
                    || str_contains(Str::lower((string) __('identity.product_type.'.$type->value, locale: 'ar')), $needle);
            })
            ->map(fn (ProductType $type): string => $type->value)
            ->all();

        return $query->where(function (Builder $match) use ($search, $normalizedContact, $matchingTypes): void {
            $match
                ->where('public_id', 'like', "%{$search}%")
                ->orWhere('business_name->en', 'like', "%{$search}%")
                ->orWhere('business_name->ar', 'like', "%{$search}%")
                ->orWhereHas('user', function (Builder $user) use ($search, $normalizedContact): void {
                    $user->where(function (Builder $contact) use ($search, $normalizedContact): void {
                        $contact
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone_e164', 'like', "%{$search}%");

                        if ($normalizedContact !== '' && $normalizedContact !== $search) {
                            $contact
                                ->orWhere('email', 'like', "%{$normalizedContact}%")
                                ->orWhere('phone_e164', 'like', "%{$normalizedContact}%");
                        }
                    });
                })
                ->orWhereHas('documents', fn (Builder $document) => $document
                    ->where('public_id', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhere('doc_type', 'like', "%{$search}%"))
                ->orWhereHas('primaryGovernorate', fn (Builder $governorate) => $governorate
                    ->where('public_id', 'like', "%{$search}%")
                    ->orWhere('name->en', 'like', "%{$search}%")
                    ->orWhere('name->ar', 'like', "%{$search}%"))
                ->orWhereHas('primaryCity', fn (Builder $city) => $city
                    ->where('public_id', 'like', "%{$search}%")
                    ->orWhere('name->en', 'like', "%{$search}%")
                    ->orWhere('name->ar', 'like', "%{$search}%"))
                ->orWhereHas('coverageAreas.city', fn (Builder $city) => $city
                    ->where('public_id', 'like', "%{$search}%")
                    ->orWhere('name->en', 'like', "%{$search}%")
                    ->orWhere('name->ar', 'like', "%{$search}%")
                    ->orWhereHas('governorate', fn (Builder $governorate) => $governorate
                        ->where('public_id', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%")))
                ->when($matchingTypes !== [], fn (Builder $types) => $types
                    ->orWhereHas('approvedProductTypes', fn (Builder $type) => $type
                        ->whereNull('revoked_at')
                        ->whereIn('product_type', $matchingTypes)));
        });
    }
}
