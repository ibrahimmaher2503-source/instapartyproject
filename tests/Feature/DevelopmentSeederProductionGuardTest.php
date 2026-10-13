<?php

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Database\Seeders\IdentityDevelopmentSeeder;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

it('skips development credentials in production without changing an existing admin', function () {
    $application = app();
    $environment = $application->environment();
    $application->detectEnvironment(static fn (): string => 'production');

    try {
        app(AdminUserSeeder::class)->run();
        app(IdentityDevelopmentSeeder::class)->run();

        expect(User::count())->toBe(0);

        $emptyDatabaseCounts = [User::count(), Role::count(), Governorate::count(), City::count()];

        expect(fn () => app(DatabaseSeeder::class)->run())
            ->toThrow(RuntimeException::class, 'Development database seeding is unavailable outside local/testing.');
        expect([User::count(), Role::count(), Governorate::count(), City::count()])->toBe($emptyDatabaseCounts);

        Role::findOrCreate('customer', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('super_admin', 'web');

        $admin = User::factory()->create([
            'email' => 'admin@instaparty.local',
            'name' => 'Existing admin',
            'password' => Hash::make(Str::random(48)),
        ]);
        $admin->assignRole('customer');
        $passwordHash = $admin->getRawOriginal('password');

        app(AdminUserSeeder::class)->run();
        app(IdentityDevelopmentSeeder::class)->run();

        $existingDatabaseCounts = [Role::count(), Governorate::count(), City::count()];

        expect(fn () => app(DatabaseSeeder::class)->run())
            ->toThrow(RuntimeException::class, 'Development database seeding is unavailable outside local/testing.');

        $admin = $admin->fresh();

        expect(User::count())->toBe(1)
            ->and([Role::count(), Governorate::count(), City::count()])->toBe($existingDatabaseCounts)
            ->and($admin->name)->toBe('Existing admin')
            ->and($admin->getRawOriginal('password'))->toBe($passwordHash)
            ->and($admin->hasRole('customer'))->toBeTrue()
            ->and($admin->hasRole('super_admin'))->toBeFalse();
    } finally {
        $application->detectEnvironment(static fn (): string => $environment);
    }
});
