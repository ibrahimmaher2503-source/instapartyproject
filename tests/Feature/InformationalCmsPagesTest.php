<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Models\CmsPage;
use Database\Seeders\InformationalCmsPagesSeeder;

it('seeds editable localized informational pages and renders a safe document outline', function (): void {
    $this->seed(InformationalCmsPagesSeeder::class);

    $privacy = CmsPage::query()->where('slug', 'privacy')->sole();
    $privacy->setTranslation('title', 'en', 'Admin edited privacy title');
    $privacy->setTranslation('body', 'ar', $privacy->getTranslation('body', 'ar').'<script>alert("xss")</script>');
    $privacy->save();

    $this->seed(InformationalCmsPagesSeeder::class);

    expect(CmsPage::query()->whereIn('slug', ['about', 'privacy', 'terms'])->count())->toBe(3)
        ->and($privacy->fresh()->getTranslation('title', 'en'))->toBe('Admin edited privacy title');

    $this->get('/ar/p/privacy')
        ->assertOk()
        ->assertSee('في هذه الصفحة')
        ->assertSee('href="#', false)
        ->assertSee('name="description"', false)
        ->assertDontSee('<script', false);
});
