<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Actions\GetHomepageBlocksAction;
use App\Modules\Shared\Application\Actions\SaveHomeBlockAction;
use App\Modules\Shared\Domain\Enums\HomeBlockType;
use App\Modules\Shared\Filament\Resources\HomeBlockResource\Pages\EditHomeBlock;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function qaVerifyHeroBlock(string $name, int $position, string $headline, string $arabic = 'دعوة للاختبار', bool $visible = true): array
{
    return [
        'block_type' => HomeBlockType::HeroCarousel->value,
        'name' => $name,
        'position' => $position,
        'is_visible' => $visible,
        'payload' => [
            'slides' => [[
                'headline' => ['en' => $headline, 'ar' => $arabic],
                'image_url' => 'https://example.test/qa-hero.jpg',
                'cta_label' => ['en' => 'Open', 'ar' => 'افتح'],
                'cta_url' => '/qa-destination',
            ]],
        ],
    ];
}

it('edits homepage block content, ordering and visibility and propagates both locales', function (): void {
    $save = app(SaveHomeBlockAction::class);
    $get = app(GetHomepageBlocksAction::class);
    $first = $save->execute(null, qaVerifyHeroBlock('First QA block', 10, 'Original QA headline'));
    $second = $save->execute(null, qaVerifyHeroBlock('Second QA block', 20, 'Second QA headline'));

    expect(array_column($get->execute('en'), 'name'))->toBe(['First QA block', 'Second QA block']);
    $this->get('/en')->assertOk()->assertSee('Original QA headline');

    $save->execute($first, qaVerifyHeroBlock('First QA block', 10, 'Updated QA headline', 'عنوان الاختبار المحدث'));
    $this->get('/en')->assertOk()->assertSee('Updated QA headline')->assertSee('/en/qa-destination', false);
    $this->get('/ar')->assertOk()->assertSee('عنوان الاختبار المحدث');

    $save->execute($first, qaVerifyHeroBlock('First QA block', 30, 'Updated QA headline', 'عنوان الاختبار المحدث'));
    expect(array_column($get->execute('en'), 'name'))->toBe(['Second QA block', 'First QA block']);
    $this->get('/en')->assertOk()->assertSee('Second QA headline');

    $save->execute($second, qaVerifyHeroBlock('Second QA block', 20, 'Second QA headline', 'دعوة للاختبار', false));

    $english = $get->execute('en');
    $arabic = $get->execute('ar');

    expect(array_column($english, 'name'))->toBe(['First QA block'])
        ->and($english[0]['payload']['slides'][0]['headline'])->toBe('Updated QA headline')
        ->and($arabic[0]['payload']['slides'][0]['headline'])->toBe('عنوان الاختبار المحدث');

    $this->get('/en')->assertOk()->assertSee('Updated QA headline')->assertDontSee('Second QA headline');
    $this->get('/ar')->assertOk()->assertSee('عنوان الاختبار المحدث')->assertDontSee('Second QA headline');
});

it('rejects an unsafe CTA URL and preserves the existing block', function (): void {
    $action = app(SaveHomeBlockAction::class);
    $block = $action->execute(null, qaVerifyHeroBlock('Safe QA block', 1, 'Safe headline'));
    $unsafe = qaVerifyHeroBlock('Changed QA block', 2, 'Changed headline');
    $unsafe['payload']['slides'][0]['cta_url'] = 'javascript:alert(1)';

    expect(fn () => $action->execute($block, $unsafe))->toThrow(ValidationException::class);
    expect($block->refresh()->name)->toBe('Safe QA block')
        ->and($block->payload['slides'][0]['headline']['en'])->toBe('Safe headline');
});

it('saves the actual edit form when the immutable block type is omitted', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'update'] as $operation) {
        $admin->givePermissionTo(Permission::findOrCreate($operation.'_home::block', 'web'));
    }
    $this->actingAs($admin);
    $block = app(SaveHomeBlockAction::class)->execute(null, qaVerifyHeroBlock('Original block', 10, 'Saved headline'));

    Livewire::test(EditHomeBlock::class, ['record' => $block->public_id])
        ->fillForm(['name' => 'Edited block'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($block->fresh()->name)->toBe('Edited block')
        ->and($block->fresh()->block_type)->toBe(HomeBlockType::HeroCarousel)
        ->and($block->fresh()->payload['slides'][0]['headline']['en'])->toBe('Saved headline');
});
