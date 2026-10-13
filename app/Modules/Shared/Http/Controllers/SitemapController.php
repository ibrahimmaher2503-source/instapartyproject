<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Http\Middleware\SetStorefrontLocaleMiddleware;
use Illuminate\Http\Response;

final class SitemapController
{
    private const PUBLIC_ROUTE_NAMES = [
        'storefront.home',
        'storefront.search',
        'storefront.vendors.index',
        'storefront.join-us',
    ];

    public function __invoke(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $urls = [];

        foreach (SetStorefrontLocaleMiddleware::SUPPORTED_LOCALES as $locale) {
            foreach (self::PUBLIC_ROUTE_NAMES as $routeName) {
                $urls[] = $baseUrl.route($routeName, ['locale' => $locale], false);
            }
        }

        $entries = array_map(
            static fn (string $url): string => '    <url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>',
            $urls,
        );

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .implode("\n", $entries)."\n"
            ."</urlset>\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
