<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

final class VendorLifecycleQaFixtures
{
    public readonly string $runId;

    public function __construct(?string $runId = null)
    {
        $this->runId = $runId ?? 'qa-'.Str::lower((string) Str::ulid());

        if (! preg_match('/^qa-[a-z0-9-]+$/', $this->runId)) {
            throw new InvalidArgumentException('QA run identifiers must start with qa- and contain only letters, numbers, and hyphens.');
        }
    }

    /** @return array<string, User|VendorProfile> */
    public function createPrincipals(): array
    {
        VendorLifecycleQaEnvironment::assertIsolated();

        foreach (['super_admin', 'admin', 'support', 'finance', 'vendor', 'customer'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $superAdmin = $this->user('super-admin', 'super_admin');
        $superAdmin->assignRole('admin');

        $principals = [
            'super_admin' => $superAdmin,
            'support' => $this->user('support', 'support'),
            'finance' => $this->user('finance', 'finance'),
            'customer' => $this->user('customer', 'customer'),
        ];

        foreach (['rental', 'sale', 'digital'] as $type) {
            $user = $this->user($type.'-vendor', 'vendor');
            $principals[$type.'_vendor'] = VendorProfile::factory()->pending()->create([
                'user_id' => $user->id,
                'business_name' => [
                    'en' => "[{$this->runId}] {$type} vendor",
                    'ar' => "[{$this->runId}] مورد {$type}",
                ],
                'slug' => $this->runId.'-'.$type,
            ]);
        }

        return $principals;
    }

    public function cleanup(): int
    {
        VendorLifecycleQaEnvironment::assertIsolated();

        $users = User::withTrashed()
            ->where('email', 'like', '%'.$this->runId.'%@example.test')
            ->get();

        VendorProfile::withTrashed()
            ->whereIn('user_id', $users->modelKeys())
            ->each(static fn (VendorProfile $profile) => $profile->forceDelete());

        foreach ($users as $user) {
            $user->forceDelete();
        }

        return $users->count();
    }

    private function user(string $label, string $role): User
    {
        $user = User::factory()->phoneVerified()->create([
            'name' => "[{$this->runId}] {$label}",
            'email' => "{$this->runId}.{$label}@example.test",
        ]);
        $user->assignRole($role);

        return $user;
    }
}
