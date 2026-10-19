<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Promotions\Domain\Models\PromoCode;
use App\Modules\Promotions\Domain\Models\PromoCodeUse;
use App\Modules\Promotions\Filament\Resources\PromoCodeResource;
use App\Modules\Promotions\Filament\Resources\PromoCodeResource\Pages\CreatePromoCode;
use App\Modules\Promotions\Filament\Resources\PromoCodeResource\Pages\EditPromoCode;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'create', 'update', 'delete'] as $operation) {
        $admin->givePermissionTo(Permission::findOrCreate($operation.'_promo::code', 'web'));
    }
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('refuses deletion of a used code without losing its immutable usage', function (): void {
    $promo = PromoCode::query()->create(['code' => 'QA-USED', 'type' => 'percentage', 'scope' => 'platform', 'discount_percent' => 10, 'is_active' => true]);
    $customer = User::factory()->asCustomer()->create();
    $usage = PromoCodeUse::query()->create([
        'promo_code_id' => $promo->id, 'user_id' => $customer->id,
        'discount_minor' => 100, 'discount_currency' => 'EGP', 'used_at' => now(),
    ]);
    Livewire::test(EditPromoCode::class, ['record' => $promo->getRouteKey()])
        ->callAction('delete')->assertNotified();
    expect(PromoCode::query()->whereKey($promo->id)->exists())->toBeTrue()
        ->and($usage->fresh())->not->toBeNull();
});

it('creates a promotion through its authorized reactive form', function (string $type): void {
    $this->get(PromoCodeResource::getUrl('create'))->assertOk();
    Livewire::test(CreatePromoCode::class)->fillForm([
        'code' => 'QA-'.$type, 'type' => $type, 'scope' => 'platform',
        'discount_percent' => 10, 'discount_minor' => 1500, 'is_active' => true,
    ])->call('create')->assertHasNoFormErrors();
    $promo = PromoCode::query()->sole();
    expect($promo->code)->toBe('QA-'.$type)->and($promo->type->value)->toBe($type);
})->with(['percentage', 'fixed']);

it('shows required-field errors when a promotion is submitted empty', function (): void {
    $form = Livewire::test(CreatePromoCode::class);
    $fields = collect($form->instance()->form->getFlatComponents())
        ->filter(fn ($field): bool => method_exists($field, 'getName'))
        ->keyBy(fn ($field): string => $field->getName());

    expect($fields['code']->isRequired())->toBeFalse()
        ->and($fields['code']->isMarkedAsRequired())->toBeTrue();

    $form
        ->call('create')
        ->assertHasFormErrors(['code' => 'required', 'type' => 'required']);

    expect(PromoCode::query()->count())->toBe(0);
});

it('edits an existing promotion through its authorized form', function (): void {
    $promo = PromoCode::query()->create(['code' => 'QA-OLD', 'type' => 'percentage', 'scope' => 'platform', 'discount_percent' => 10, 'is_active' => true]);
    $this->get(PromoCodeResource::getUrl('edit', ['record' => $promo]))->assertOk();
    Livewire::test(EditPromoCode::class, ['record' => $promo->getRouteKey()])
        ->fillForm(['discount_percent' => 20])->call('save')->assertHasNoFormErrors();
    expect($promo->fresh()->discount_percent)->toBe(20)->and($promo->fresh()->code)->toBe('QA-OLD');
});

it('deletes an unused promotion through the existing delete action', function (): void {
    $promo = PromoCode::query()->create(['code' => 'QA-UNUSED', 'type' => 'percentage', 'scope' => 'platform', 'discount_percent' => 10]);
    Livewire::test(EditPromoCode::class, ['record' => $promo->getRouteKey()])->callAction('delete');
    expect(PromoCode::query()->whereKey($promo->id)->exists())->toBeFalse();
});
