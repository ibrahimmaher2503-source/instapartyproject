<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        fake()->seed(2026050301);

        $adminEmail = 'admin@instaparty.local';
        $admin = User::query()->where('email', $adminEmail)->first();

        if ($admin === null) {
            $adminPassword = config('app.admin_password');

            if (! is_string($adminPassword) || $adminPassword === '') {
                throw new RuntimeException('Configure a strong ADMIN_INITIAL_PASSWORD before seeding a new admin account.');
            }

            $passwordValidator = Validator::make(
                ['password' => $adminPassword],
                ['password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()]],
            );

            if ($passwordValidator->fails()) {
                throw new RuntimeException('ADMIN_INITIAL_PASSWORD does not meet the configured admin password policy.');
            }

            $admin = $this->firstOrCreateFactoryModel(
                User::factory()->phoneVerified()->make([
                    'public_id' => $this->stablePublicId('user:admin@instaparty.local'),
                    'name' => 'InstaParty Admin',
                    'email' => $adminEmail,
                    'phone_e164' => '+20000000000',
                    'password' => $adminPassword,
                    'email_verified_at' => now(),
                    'status' => 'active',
                ]),
                ['email' => $adminEmail],
            );
        }

        $admin->syncRoles(['admin', 'super_admin']);
    }
}
