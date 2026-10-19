<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use Filament\Facades\Filament;
use Filament\Livewire\GlobalSearch;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('returns matching results and an empty result set for ordinary admin global searches', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(collect([
        'view_any_subscription::plan',
        'view_subscription::plan',
        'view_any_vendor::subscription',
        'view_any_subscription::payment',
        'view_any_subscription::invoice',
        'view_any_subscription::audit::entry',
        'view_any_withdrawals::queue',
        'view_any_withdrawal',
        'view_any_wallet::ledger::viewer',
        'view_any_commission::rules',
    ])->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'))->all());

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $searchableResources = array_filter(
        Filament::getResources(),
        fn (string $resource): bool => $resource::getRecordTitleAttribute() !== null,
    );
    $accessibleResources = array_filter($searchableResources, fn (string $resource): bool => $resource::canGloballySearch());
    $nonSearchableResources = array_diff_key($searchableResources, $accessibleResources);

    expect($accessibleResources)->toHaveCount(11)
        ->and(array_values($nonSearchableResources))->toBe(['BezhanSalleh\\FilamentShield\\Resources\\RoleResource']);

    $plan = SubscriptionPlan::factory()->free()->create([
        'name' => ['en' => 'Cairo Celebration Plan', 'ar' => 'خطة احتفالات القاهرة'],
    ]);

    $matchingSearch = Livewire::test(GlobalSearch::class)
        ->set('search', 'Cairo')
        ->assertSuccessful()
        ->assertSee('Cairo Celebration Plan');

    $matchingResults = $matchingSearch->instance()->getResults()->getCategories()->flatten(1);

    expect($matchingResults)->toHaveCount(1)
        ->and($matchingResults->first()->title)->toBe('Cairo Celebration Plan')
        ->and(parse_url($matchingResults->first()->url, PHP_URL_PATH))->toBe('/admin/subscription-plans')
        ->and(parse_url($matchingResults->first()->url, PHP_URL_QUERY))->toContain('tableAction=view');

    $emptySearch = Livewire::test(GlobalSearch::class)
        ->set('search', 'Joy')
        ->assertSuccessful();

    expect($emptySearch->instance()->getResults()->getCategories())->toBeEmpty();
});
