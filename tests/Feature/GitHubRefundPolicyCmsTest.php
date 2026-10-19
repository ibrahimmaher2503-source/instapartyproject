<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Enums\CmsSlug;
use App\Modules\Shared\Domain\Models\CmsPage;

it('renders the existing published refund policy through the localized CMS route', function (string $locale): void {
    $page = CmsPage::query()->create([
        'slug' => CmsSlug::RefundPolicy,
        'title' => ['en' => 'CMS refund policy', 'ar' => 'سياسة الاسترداد من إدارة المحتوى'],
        'body' => ['en' => 'Published CMS test content.', 'ar' => 'محتوى اختبار منشور من إدارة المحتوى.'],
        'is_published' => true,
        'published_at' => now(),
    ]);

    expect($page->fresh()->slug)->toBe(CmsSlug::RefundPolicy);
    $this->get("/{$locale}/p/refund-policy")->assertOk()
        ->assertSee($page->getTranslation('title', $locale))
        ->assertSee($page->getTranslation('body', $locale));

    $page->update(['is_published' => false]);
    $this->get("/{$locale}/p/refund-policy")->assertNotFound();
})->with(['en', 'ar']);

it('does not substitute invented refund policy text when CMS content is missing', function (): void {
    $this->get('/en/p/refund-policy')->assertNotFound();
});
