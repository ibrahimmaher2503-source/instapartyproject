<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Support\Domain\Models\FaqCategory;
use App\Modules\Support\Domain\Models\FaqItem;
use App\Modules\Support\Filament\Resources\FaqItemResource;
use App\Modules\Support\Filament\Resources\FaqItemResource\Pages\CreateFaqItem;
use App\Modules\Support\Filament\Resources\FaqItemResource\Pages\EditFaqItem;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'create', 'update'] as $operation) {
        $admin->givePermissionTo(Permission::findOrCreate($operation.'_faq::item', 'web'));
    }
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('creates an FAQ through the authorized form and persists both translations', function (): void {
    $category = FaqCategory::query()->create(['slug' => 'booking', 'name' => ['en' => 'Booking', 'ar' => 'الحجز'], 'sort_order' => 0, 'is_active' => true]);
    $this->get(FaqItemResource::getUrl('create'))->assertOk();
    Livewire::test(CreateFaqItem::class)
        ->fillForm(['faq_category_id' => $category->id, 'question' => 'How to book?', 'answer' => '<p>Choose a service.</p>', 'sort_order' => 0, 'is_active' => true])
        ->set('activeLocale', 'ar')
        ->fillForm(['question' => 'كيف أحجز؟', 'answer' => '<p>اختر خدمة.</p>'])
        ->call('create')->assertHasNoFormErrors();
    $item = FaqItem::query()->sole();
    expect($item->getTranslation('question', 'en'))->toBe('How to book?')
        ->and($item->getTranslation('question', 'ar'))->toBe('كيف أحجز؟');
});

it('edits an FAQ without losing its other translation', function (): void {
    $category = FaqCategory::query()->create(['slug' => 'booking', 'name' => ['en' => 'Booking', 'ar' => 'الحجز'], 'sort_order' => 0, 'is_active' => true]);
    $item = FaqItem::query()->create(['faq_category_id' => $category->id, 'question' => ['en' => 'Old question', 'ar' => 'السؤال القديم'], 'answer' => ['en' => 'Answer', 'ar' => 'الإجابة'], 'sort_order' => 0, 'is_active' => true]);
    $this->get(FaqItemResource::getUrl('edit', ['record' => $item]))->assertOk();
    Livewire::test(EditFaqItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['question' => 'Updated question'])->call('save')->assertHasNoFormErrors();
    expect($item->fresh()->getTranslation('question', 'en'))->toBe('Updated question')
        ->and($item->fresh()->getTranslation('question', 'ar'))->toBe('السؤال القديم');
});
