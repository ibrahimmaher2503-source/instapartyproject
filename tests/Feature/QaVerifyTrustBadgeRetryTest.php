<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\TrustSafety\Domain\Models\TrustBadge;
use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages\CreateTrustBadge;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('keeps the same badge identity when a form submission is replayed after navigating back', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('create_trust::badge', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('view_any_trust::badge', 'web'));
    $this->actingAs($admin);
    $createdPublicIds = [];
    Event::listen('eloquent.created: '.TrustBadge::class, function (TrustBadge $badge) use (&$createdPublicIds): void {
        $createdPublicIds[] = $badge->public_id;
    });
    $submission = (string) Str::uuid();
    $data = [
        'name' => ['en' => 'Retry Badge', 'ar' => 'شارة إعادة المحاولة'],
        'description' => ['en' => 'Retry description', 'ar' => 'وصف إعادة المحاولة'],
        'level' => 3,
        'is_active' => true,
    ];
    Livewire::withQueryParams(['submission' => $submission])->test(CreateTrustBadge::class)
        ->fillForm($data)->call('create')->assertHasNoFormErrors()->assertDispatched('trust-badge-save-confirmed');
    $first = TrustBadge::query()->sole();
    Livewire::withQueryParams(['submission' => $submission])->test(CreateTrustBadge::class)
        ->fillForm($data)->call('create')->assertHasNoFormErrors()->assertDispatched('trust-badge-save-confirmed');
    $idempotency = DB::table('idempotency_keys')
        ->where('scope', 'internal')
        ->where('scope_key', 'internal_action')
        ->where('key', "trust-badge.create:{$admin->id}:{$submission}")
        ->sole();

    expect(TrustBadge::query()->count())->toBe(1)
        ->and(TrustBadge::query()->sole()->public_id)->toBe($first->public_id)
        ->and($createdPublicIds)->toBe([$first->public_id])
        ->and($idempotency->response_status)->toBe(200)
        ->and(json_decode($idempotency->response_body, true))->toBe($first->getKey());
});

it('starts a new submission after a successful create-and-create-another action', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    foreach (['create_trust::badge', 'view_any_trust::badge'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($admin);
    $submission = (string) Str::uuid();
    $component = Livewire::withQueryParams(['submission' => $submission])->test(CreateTrustBadge::class);
    foreach (['First', 'Second'] as $name) {
        $component->fillForm([
            'name' => ['en' => $name.' badge', 'ar' => 'شارة '.$name],
            'description' => ['en' => $name.' description', 'ar' => 'وصف '.$name],
            'level' => 3, 'is_active' => true,
        ])->call('create', true)->assertHasNoFormErrors();
        expect($component->get('submissionId'))->not->toBe($submission);
    }
    expect(TrustBadge::query()->count())->toBe(2);
});

it('throttles the create-another action without a second click handler', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    foreach (['create_trust::badge', 'view_any_trust::badge'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $this->actingAs($admin);
    $html = Livewire::test(CreateTrustBadge::class)->html();
    expect(preg_match('/<button\b(?=[^>]*wire:target="createAnother")[^>]*>/s', $html, $button))->toBe(1)
        ->and($button[0])->toContain('wire:target="createAnother"')
        ->and($button[0])->toContain('wire:loading.attr="disabled"')
        ->and($button[0])->toContain('wire:click.throttle.500ms="createAnother"')
        ->and($button[0])->not->toContain('wire:click="createAnother"')
        ->and($button[0])->not->toContain('x-on:click=');
});

it('shows localized save-status guidance on the trust badge create page', function (string $locale, string $message): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    foreach (['create_trust::badge', 'view_any_trust::badge'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $this->actingAs($admin);
    app()->setLocale($locale);

    $component = Livewire::test(CreateTrustBadge::class);

    expect($component->html())->toContain('role="alert"')
        ->toContain($message)
        ->and(implode("\n", $component->effects['scripts'] ?? []))->toContain("\$wire.\$hook('commit'")
        ->toContain('trust-badge-save-unknown');
})->with([
    'English' => ['en', 'The request failed, so we could not confirm whether this badge was saved'],
    'Arabic' => ['ar', 'تعذّر التأكد مما إذا كانت الشارة قد حُفظت'],
]);

it('does not confirm a badge save when form validation fails', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    foreach (['create_trust::badge', 'view_any_trust::badge'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $this->actingAs($admin);

    Livewire::test(CreateTrustBadge::class)
        ->call('create')
        ->assertHasFormErrors()
        ->assertNotDispatched('trust-badge-save-confirmed');
});
