<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevalidateFrontendCatalogListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @var int[] Exponential backoff in seconds: 5s, 25s, 125s */
    public array $backoff = [5, 25, 125];

    public function handle(mixed $event): void
    {
        $url = config('services.frontend.revalidate_url');
        $secret = config('services.frontend.revalidate_secret');

        if (blank($url) || blank($secret)) {
            Log::warning('RevalidateFrontendCatalogListener: FRONTEND_REVALIDATE_URL or FRONTEND_REVALIDATE_SECRET not set — skipping cache revalidation');

            return;
        }

        Http::withHeaders(['X-Revalidate-Secret' => $secret])
            ->post($url, ['tag' => 'catalog'])
            ->throw();
    }
}
