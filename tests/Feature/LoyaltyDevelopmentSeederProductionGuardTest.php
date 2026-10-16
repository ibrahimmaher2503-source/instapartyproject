<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Database\Seeders\LoyaltyDevelopmentSeeder;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;

it('skips development loyalty records in production and keeps them available in testing', function (): void {
    VendorProfile::factory()->approved()->create(['slug' => 'joy-rentals-cairo']);
    VendorProfile::factory()->approved()->create(['slug' => 'sweet-table-studio']);
    User::factory()->asCustomer()->create();
    User::factory()->asCustomer()->create();

    app()->detectEnvironment(fn (): string => 'production');

    try {
        app(LoyaltyDevelopmentSeeder::class)->run();

        expect(LoyaltyProgram::query()->count())->toBe(0)
            ->and(LoyaltyLedgerEntry::query()->count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }

    app(LoyaltyDevelopmentSeeder::class)->run();

    expect(LoyaltyProgram::query()->count())->toBe(2)
        ->and(LoyaltyLedgerEntry::query()->count())->toBe(10);
});
