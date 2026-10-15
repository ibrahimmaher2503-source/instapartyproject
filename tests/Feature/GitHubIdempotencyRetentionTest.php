<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\IdempotencyKey;
use App\Modules\Payments\Filament\Resources\IdempotencyKeyResource;
use App\Modules\Payments\Infrastructure\Repositories\EloquentIdempotencyKeyRepository;
use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('separates routine interface retries without deleting business replay keys', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_idempotency::key', 'web'));
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $business = IdempotencyKey::factory()->create(['route' => 'payments.capture']);
    $webhook = IdempotencyKey::factory()->create([
        'scope' => 'internal',
        'route' => 'internal_webhook:livewire.update:event',
    ]);
    $noise = IdempotencyKey::factory()->create(['route' => 'default.livewire.update']);
    $pathNoise = IdempotencyKey::factory()->create(['route' => 'livewire/update']);
    $page = IdempotencyKeyResource::getPages()['index']->getPage();
    Livewire::test($page)
        ->assertCanSeeTableRecords([$business, $webhook])
        ->assertCanNotSeeTableRecords([$noise, $pathNoise])
        ->removeTableFilter('business_operations')
        ->assertCanSeeTableRecords([$business, $webhook, $noise, $pathNoise]);
    expect(IdempotencyKey::query()->count())->toBe(4);
});

it('filters expired business keys at the exact expiry boundary', function (): void {
    $this->freezeTime();
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_idempotency::key', 'web'));
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $expired = IdempotencyKey::factory()->create(['route' => 'payments.capture', 'expires_at' => now()]);
    $live = IdempotencyKey::factory()->create(['route' => 'payments.capture', 'expires_at' => now()->addDay()]);
    $noise = IdempotencyKey::factory()->create(['route' => 'default.livewire.update', 'expires_at' => now()]);
    $page = IdempotencyKeyResource::getPages()['index']->getPage();

    Livewire::test($page)
        ->filterTable('expired', '1')
        ->assertCanSeeTableRecords([$expired])
        ->assertCanNotSeeTableRecords([$live, $noise])
        ->filterTable('expired', '0')
        ->assertCanSeeTableRecords([$live])
        ->assertCanNotSeeTableRecords([$expired, $noise])
        ->removeTableFilter('expired')
        ->assertSet('tableFilters.expired.value', null)
        ->assertCanSeeTableRecords([$expired, $live]);
});

it('purges only expired keys at the scheduler retention boundary', function (): void {
    $this->freezeTime();
    $expired = IdempotencyKey::factory()->create(['expires_at' => now()->subSecond()]);
    $boundary = IdempotencyKey::factory()->create(['expires_at' => now()]);
    $expiredInternal = IdempotencyKey::factory()->create([
        'scope' => 'internal',
        'scope_key' => 'internal_action',
        'route' => 'internal_action:expired-retention',
        'expires_at' => now()->subSecond(),
    ]);
    $live = IdempotencyKey::factory()->create(['expires_at' => now()->addDay()]);
    $webhook = IdempotencyKey::factory()->create([
        'scope' => 'internal',
        'route' => 'internal_webhook:retention',
        'ttl_seconds' => 2592000,
        'created_at' => now()->subDays(2),
        'expires_at' => now()->addDays(28),
    ]);
    expect(app(EloquentIdempotencyKeyRepository::class)->purgeExpired())->toBe(3);
    expect(IdempotencyKey::find($expired->id))->toBeNull()
        ->and(IdempotencyKey::find($boundary->id))->toBeNull()
        ->and(IdempotencyKey::find($expiredInternal->id))->toBeNull()
        ->and(IdempotencyKey::find($live->id))->not->toBeNull()
        ->and(IdempotencyKey::find($webhook->id))->not->toBeNull();
});

it('stores and replays internal keys until their configured expiry', function (string $scope, int $ttl): void {
    $this->freezeTime();
    config()->set('idempotency.ttl.'.$scope, $ttl);
    $service = app(IdempotencyService::class);
    $key = 'configured-retention-'.$scope;
    $calls = 0;
    $callback = function () use (&$calls): array {
        $calls++;

        return ['attempt' => $calls];
    };

    expect($service->remember($scope, $key, ['amount' => 100], $callback))->toBe(['attempt' => 1]);
    $row = DB::table('idempotency_keys')->where('key', $key)->first();
    expect($row->scope)->toBe('internal')
        ->and($row->route)->toBe($scope.':'.$key)
        ->and((int) $row->ttl_seconds)->toBe($ttl);
    expect(fn () => $service->remember($scope, $key, ['amount' => 200], $callback))
        ->toThrow(DuplicateIdempotencyKeyWithDifferentPayloadException::class);

    $this->travel($ttl - 1)->seconds();
    expect($service->hasBeenUsed($scope, $key))->toBeTrue()
        ->and($service->hasBeenUsed($scope === 'internal_webhook' ? 'internal_action' : 'internal_webhook', $key))->toBeFalse()
        ->and($service->remember($scope, $key, ['amount' => 100], $callback))->toBe(['attempt' => 1])
        ->and($calls)->toBe(1)
        ->and(app(EloquentIdempotencyKeyRepository::class)->purgeExpired())->toBe(0);

    $this->travel(1)->seconds();
    expect($service->hasBeenUsed($scope, $key))->toBeFalse()
        ->and(app(EloquentIdempotencyKeyRepository::class)->purgeExpired())->toBe(1);
})->with([
    'configured webhook window' => ['internal_webhook', 172800],
    'configured action window' => ['internal_action', 1800],
]);
