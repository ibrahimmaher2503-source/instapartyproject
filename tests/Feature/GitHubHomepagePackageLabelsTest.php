<?php

declare(strict_types=1);

use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource\Pages\CreatePackageRecommendation;
use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('distinguishes package name and description labels and their required validation errors', function (string $locale): void {
    app()->setLocale($locale);
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('create_package::recommendation', 'web'));
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $component = Livewire::test(CreatePackageRecommendation::class)
        ->fillForm([
            'name.en' => '', 'name.ar' => 'باقة اختبار',
            'description.en' => '', 'description.ar' => 'وصف الاختبار',
            'slug' => 'github-package-validation',
        ])
        ->call('create')
        ->assertHasFormErrors(['name.en' => 'required', 'description.en' => 'required']);

    $nameLabel = __('discovery::discovery.package_recommendations.name_en');
    $descriptionLabel = __('discovery::discovery.package_recommendations.description_en');
    $component->assertSee($nameLabel)->assertSee($descriptionLabel);
    $errors = $component->instance()->getErrorBag();
    expect($errors->first('data.name.en'))->toContain($nameLabel)
        ->and($errors->first('data.description.en'))->toContain($descriptionLabel)
        ->and($errors->first('data.name.en'))->not->toBe($errors->first('data.description.en'));
})->with(['en', 'ar']);
