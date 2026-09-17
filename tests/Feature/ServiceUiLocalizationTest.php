<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Filament\Pages\ImportDigitalServicesPage;
use App\Modules\Catalog\Filament\Pages\ImportRentalServicesPage;
use App\Modules\Catalog\Filament\Pages\ImportSaleServicesPage;

it('localizes the three admin service import page titles', function (): void {
    $pages = [
        ImportRentalServicesPage::class => ['en' => 'Import Rental Services', 'ar' => 'استيراد خدمات التأجير'],
        ImportSaleServicesPage::class => ['en' => 'Import Sale Services', 'ar' => 'استيراد خدمات البيع'],
        ImportDigitalServicesPage::class => ['en' => 'Import Digital Services', 'ar' => 'استيراد الخدمات الرقمية'],
    ];

    foreach ($pages as $pageClass => $titles) {
        $page = new $pageClass;

        foreach ($titles as $locale => $title) {
            app()->setLocale($locale);

            expect($page->getTitle())->toBe($title);
        }
    }
});

it('keeps every service status label translated in both supported locales', function (): void {
    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);

        foreach (ServiceStatus::cases() as $status) {
            expect($status->getLabel())
                ->not->toBe($status->value)
                ->not->toBe('catalog.status.'.$status->value);
        }
    }
});
