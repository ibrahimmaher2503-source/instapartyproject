<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LaunchHardeningFixturesSeeder extends Seeder
{
    public function run(): void
    {
        // ----------------------------------------------------------------
        // 1. Find or create the E2E customer user
        // ----------------------------------------------------------------
        $userId = DB::table('users')
            ->where('email', 'e2e.customer@instaparty.test')
            ->value('id');

        if (! $userId) {
            $userId = DB::table('users')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'name' => 'E2E Customer',
                'email' => 'e2e.customer@instaparty.test',
                'password' => Hash::make('password'),
                'phone' => '+201234560001',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("LaunchHardeningFixturesSeeder: e2e customer user_id={$userId}");

        // ----------------------------------------------------------------
        // 2. Assign the customer role (spatie/laravel-permission)
        // ----------------------------------------------------------------
        $customerRoleId = DB::table('roles')
            ->where('name', 'customer')
            ->value('id');

        if ($customerRoleId) {
            $alreadyHasRole = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $userId)
                ->where('role_id', $customerRoleId)
                ->exists();

            if (! $alreadyHasRole) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $customerRoleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }
        }

        // ----------------------------------------------------------------
        // 3. Find or create a customer_profile row
        // ----------------------------------------------------------------
        $hasProfile = DB::table('customer_profiles')
            ->where('user_id', $userId)
            ->exists();

        if (! $hasProfile) {
            DB::table('customer_profiles')->insert([
                'public_id' => (string) Str::ulid(),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('LaunchHardeningFixturesSeeder: customer profile ensured.');
    }
}
