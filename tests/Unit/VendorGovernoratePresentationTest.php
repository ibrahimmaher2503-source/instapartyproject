<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Services\StorefrontText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('maps primary and coverage governorates once and falls back to the city relationship', function (): void {
    $governorate = new Governorate(['public_id' => 'primary', 'name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
    $other = new Governorate(['public_id' => 'coverage', 'name' => ['en' => 'Giza', 'ar' => 'الجيزة']]);
    $city = (new City)->setRelation('governorate', $governorate);
    $area = (new VendorCoverageArea)->setRelation('city', (new City)->setRelation('governorate', $other));
    $vendor = (new VendorProfile)->setRelation('primaryGovernorate', $governorate)
        ->setRelation('primaryCity', $city)->setRelation('coverageAreas', collect([$area, $area]));
    $text = new StorefrontText;
    expect($text->vendorGovernorates($vendor, 'en')->pluck('name')->all())->toBe(['Cairo', 'Giza'])
        ->and($text->vendorGovernorates($vendor, 'ar')->pluck('name')->all())->toBe(['القاهرة', 'الجيزة']);
    $vendor->setRelation('primaryGovernorate', null);
    expect($text->vendorGovernorates($vendor, 'en')->pluck('name')->all())->toBe(['Cairo', 'Giza'])
        ->and($text->vendorGovernorates(null))->toBeEmpty();
});
