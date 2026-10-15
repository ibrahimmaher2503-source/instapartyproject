<?php

declare(strict_types=1);

use App\Modules\Shared\Filament\Forms\BilingualTabErrorFeedback;
use Livewire\Component as LivewireComponent;

it('counts errors for translation tabs and opens the first affected locale', function (): void {
    $component = new class extends LivewireComponent {};
    $component->setErrorBag([
        'data.name.en' => ['English name is required.'],
        'data.short_description.en' => ['English description is required.'],
        'data.name.ar' => ['Arabic name is required.'],
        'data.price_minor' => ['Price is required.'],
    ]);

    expect(BilingualTabErrorFeedback::countForLocale($component, 'en'))->toBe(2)
        ->and(BilingualTabErrorFeedback::countForLocale($component, 'ar'))->toBe(1)
        ->and(BilingualTabErrorFeedback::activeTab($component))->toBe(1);

    $component->setErrorBag(['data.name.ar' => ['Arabic name is required.']]);

    expect(BilingualTabErrorFeedback::activeTab($component))->toBe(2);
});

it('provides focused accessible error handling for tabbed forms', function (): void {
    $attributes = BilingualTabErrorFeedback::focusFirstVisibleErrorAttributes();

    expect($attributes)->toHaveKey('x-init')
        ->and($attributes['x-init'])->toContain('[data-validation-error]')
        ->and($attributes['x-init'])->toContain('.focus(');
});
