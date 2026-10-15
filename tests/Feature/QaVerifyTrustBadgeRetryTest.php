<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\TrustSafety\Domain\Models\TrustBadge;
use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages\CreateTrustBadge;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('keeps the same badge identity when a form submission is replayed after navigating back', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('create_trust::badge', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('view_any_trust::badge', 'web'));
    $this->actingAs($admin);
    $submission = (string) Str::uuid();
    $data = [
        'name' => ['en' => 'Retry Badge', 'ar' => 'شارة إعادة المحاولة'],
        'description' => ['en' => 'Retry description', 'ar' => 'وصف إعادة المحاولة'],
        'level' => 3,
        'is_active' => true,
    ];
    Livewire::withQueryParams(['submission' => $submission])->test(CreateTrustBadge::class)
        ->fillForm($data)->call('create')->assertHasNoFormErrors();
    $first = TrustBadge::query()->sole();
    Livewire::withQueryParams(['submission' => $submission])->test(CreateTrustBadge::class)
        ->fillForm($data)->call('create')->assertHasNoFormErrors();
    expect(TrustBadge::query()->sole()->public_id)->toBe($first->public_id);
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
