<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Services\StorefrontText;

final class StorefrontTextFixture
{
    /** @param array<string, string> $translations */
    public function __construct(private array $translations) {}

    public function getTranslation(string $field, string $locale, bool $useFallbackLocale = true): string
    {
        return $this->translations[$field.'_'.$locale] ?? '';
    }
}

it('falls back to a usable English translation when the storefront locale is corrupted', function (): void {
    $model = new StorefrontTextFixture([
        'name_ar' => '????????',
        'name_en' => 'Birthday cake',
    ]);

    expect((new StorefrontText)->translation($model, 'name', 'ar'))->toBe('Birthday cake');
});

it('keeps a valid storefront translation in the active locale', function (): void {
    $model = new StorefrontTextFixture([
        'name_ar' => 'كيكة عيد ميلاد',
        'name_en' => 'Birthday cake',
    ]);

    expect((new StorefrontText)->translation($model, 'name', 'ar'))->toBe('كيكة عيد ميلاد');
});
