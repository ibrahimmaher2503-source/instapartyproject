<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Http\Middleware\SetStorefrontLocaleMiddleware;
use Illuminate\Http\Response;

final class RobotsTxtController
{
    public function __invoke(): Response
    {
        $privatePaths = ['/admin', '/vendor-portal', '/api', '/login'];

        foreach (SetStorefrontLocaleMiddleware::SUPPORTED_LOCALES as $locale) {
            $privatePaths[] = '/'.$locale.'/account';
            $privatePaths[] = '/'.$locale.'/auth';
        }

        $rules = implode("\n", array_map(static fn (string $path): string => 'Disallow: '.$path, $privatePaths));
        $sitemap = rtrim((string) config('app.url'), '/').route('sitemap', [], false);

        return response("User-agent: *\n{$rules}\nSitemap: {$sitemap}\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
