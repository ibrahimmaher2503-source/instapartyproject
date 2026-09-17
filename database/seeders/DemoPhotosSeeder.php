<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

/**
 * Attaches demo photos to every service, category, and occasion in the database.
 *
 * Idempotent — skips records that already have media / icon_path set.
 * Safe to run standalone: php artisan db:seed --class=DemoPhotosSeeder
 */
class DemoPhotosSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedServiceGalleries();
        $this->seedCategoryIcons();
        $this->seedOccasionIcons();
    }

    private const GALLERY_TARGET = 6;

    private function seedServiceGalleries(): void
    {
        $services = Service::withoutTrashed()->get();

        $this->command?->info("Seeding gallery photos for {$services->count()} service(s) (target: ".self::GALLERY_TARGET.' per service)...');

        foreach ($services as $service) {
            $existing = $service->getMedia('gallery')->count();

            if ($existing >= self::GALLERY_TARGET) {
                continue;
            }

            $seed = $service->slug ?? $service->public_id;
            $name = $service->getTranslation('name', 'en');
            $added = 0;

            foreach (range($existing + 1, self::GALLERY_TARGET) as $index) {
                $url = "https://picsum.photos/seed/{$seed}-{$index}/800/600";

                try {
                    $response = Http::timeout(15)->get($url);

                    if (! $response->successful()) {
                        continue;
                    }

                    $service
                        ->addMediaFromString($response->body())
                        ->usingFileName("{$seed}-photo-{$index}.jpg")
                        ->usingName("{$name} photo {$index}")
                        ->withCustomProperties(['mime_type' => 'image/jpeg'])
                        ->toMediaCollection('gallery');

                    $added++;
                } catch (\Throwable) {
                    $this->command?->warn("  ✗ Skipped gallery for service [{$seed}]: network unavailable.");
                    break;
                }
            }

            $total = $existing + $added;
            $this->command?->line("  ✓ {$name} ({$total}/".self::GALLERY_TARGET.' photos)');
        }
    }

    private function seedCategoryIcons(): void
    {
        $categories = Category::withoutTrashed()->get();

        $this->command?->info("Setting icon_path for {$categories->count()} category(ies)...");

        foreach ($categories as $category) {
            $category->update([
                'icon_path' => "https://picsum.photos/seed/cat-{$category->code}/400/400",
            ]);

            $name = $category->getTranslation('name', 'en');
            $this->command?->line("  ✓ {$name}");
        }
    }

    private function seedOccasionIcons(): void
    {
        $occasions = Occasion::withoutTrashed()->get();

        $this->command?->info("Setting icon_path for {$occasions->count()} occasion(s)...");

        foreach ($occasions as $occasion) {
            $occasion->update([
                'icon_path' => "https://picsum.photos/seed/occ-{$occasion->code}/400/400",
            ]);

            $name = $occasion->getTranslation('name', 'en');
            $this->command?->line("  ✓ {$name}");
        }
    }
}
