<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeOtpGateway;
use Tests\Fakes\FakePaymentGateway;
use Tests\Support\VendorLifecycleQaEnvironment;
use Tests\Support\VendorLifecycleQaFixtures;

beforeEach(function (): void {
    VendorLifecycleQaEnvironment::boot();
});

afterEach(function (): void {
    VendorLifecycleQaEnvironment::resetClock();
});

it('fails closed around external providers and creates every isolated QA principal', function (): void {
    $fixtures = new VendorLifecycleQaFixtures('qa-harness-principals');
    $principals = $fixtures->createPrincipals();

    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class)
        ->and(app(OtpGatewayInterface::class))->toBeInstanceOf(FakeOtpGateway::class)
        ->and($principals)->toHaveKeys([
            'super_admin',
            'support',
            'finance',
            'rental_vendor',
            'sale_vendor',
            'digital_vendor',
            'customer',
        ])
        ->and(User::query()->where('email', 'not like', '%qa-harness-principals%@example.test')->exists())->toBeFalse();
});

it('cleanup selects only users carrying the current QA run identifier', function (): void {
    $fixtures = new VendorLifecycleQaFixtures('qa-cleanup-target');
    $fixtures->createPrincipals();
    $unrelated = User::factory()->create(['email' => 'unrelated@example.test']);

    expect($fixtures->cleanup())->toBe(7)
        ->and(User::query()->whereKey($unrelated->id)->exists())->toBeTrue()
        ->and(User::withTrashed()->where('email', 'like', '%qa-cleanup-target%@example.test')->exists())->toBeFalse();
});

it('refuses a production-like database configuration', function (): void {
    $originalConnection = config('database.default');

    try {
        config()->set('database.default', 'mysql');

        expect(fn () => VendorLifecycleQaEnvironment::assertIsolated())
            ->toThrow(RuntimeException::class, 'Vendor lifecycle QA refused');
    } finally {
        config()->set('database.default', $originalConnection);
    }
});

it('denies admin lifecycle URLs to vendor support and finance roles', function (): void {
    $fixtures = new VendorLifecycleQaFixtures('qa-route-isolation');
    $principals = $fixtures->createPrincipals();
    $profile = $principals['rental_vendor'];

    foreach (['support', 'finance', 'customer'] as $role) {
        Sanctum::actingAs($principals[$role]);
        $this->postJson("/api/v1/admin/vendor-profiles/{$profile->public_id}/approve")
            ->assertForbidden();
    }

    Sanctum::actingAs($profile->user);
    $this->postJson("/api/v1/admin/vendor-profiles/{$profile->public_id}/approve")
        ->assertForbidden();
});
