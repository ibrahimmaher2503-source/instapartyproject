<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('has English and Arabic values for static Filament translation keys', function (): void {
    $keys = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file): bool => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Filament'.DIRECTORY_SEPARATOR))
        ->flatMap(function (SplFileInfo $file): array {
            preg_match_all('/__\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', $file->getContents(), $matches);

            return $matches[1];
        })
        ->reject(fn (string $key): bool => str_contains($key, '$') || str_contains($key, '{'))
        ->unique()
        ->values();

    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);
        $missing = $keys->filter(fn (string $key): bool => __($key) === $key)->values()->all();

        expect($missing)->toBe([], "Missing {$locale} Filament translations: ".implode(', ', $missing));
    }
});
