<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Shared\Http\Middleware\SetStorefrontLocaleMiddleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CustomerIntendedUrl
{
    public function redirect(Request $request, string $fallback): RedirectResponse
    {
        $intendedUrl = $request->session()->pull('url.intended');
        if (! is_string($intendedUrl) || $intendedUrl === '') {
            return redirect($fallback);
        }

        $parts = parse_url($intendedUrl);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return redirect($fallback);
        }

        $host = $parts['host'] ?? null;
        if ($host !== null && strcasecmp($host, $request->getHost()) !== 0) {
            return redirect($fallback);
        }

        if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return redirect($fallback);
        }

        $path = $parts['path'] ?? '';
        $locale = explode('/', trim($path, '/'))[0] ?? '';
        $decodedPath = rawurldecode($path);
        $segments = explode('/', $decodedPath);
        if (! str_starts_with($path, '/') || str_starts_with($path, '//')
            || ! in_array($locale, SetStorefrontLocaleMiddleware::SUPPORTED_LOCALES, true)
            || str_contains($decodedPath, '\\')
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)) {
            return redirect($fallback);
        }

        return redirect()->to($path
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : ''));
    }
}
