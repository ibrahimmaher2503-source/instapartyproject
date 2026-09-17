<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

it('masks changed bank values in the audit while retaining ordinary changed values', function (): void {
    $profile = VendorProfile::factory()->create([
        'bank_iban' => 'EG380019000500000000263180002',
        'bio' => ['en' => 'Before', 'ar' => 'قبل'],
    ]);

    app(UpdateVendorProfileAction::class)->execute($profile, [
        'bank_iban' => 'EG290019000500000000263180009',
        'bio' => ['en' => 'After', 'ar' => 'بعد'],
    ]);

    $properties = json_decode((string) DB::table('activity_log')
        ->where('subject_type', 'vendor')
        ->where('subject_id', $profile->id)
        ->where('description', 'updated_vendor_profile')
        ->value('properties'), true, flags: JSON_THROW_ON_ERROR);

    expect($properties['old']['bank_iban'])->toBe('EG38••••002')
        ->and($properties['new']['bank_iban'])->toBe('EG29••••009')
        ->and(json_decode($properties['old']['bio'], true))->toBe(['en' => 'Before', 'ar' => 'قبل'])
        ->and(json_decode($properties['new']['bio'], true))->toBe(['en' => 'After', 'ar' => 'بعد'])
        ->and(json_encode($properties))->not->toContain('EG380019000500000000263180002')
        ->not->toContain('EG290019000500000000263180009');
});
