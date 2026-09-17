<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    private const array SUPPORTED_LOCALES = ['en', 'ar'];

    private const string DEFAULT_LOCALE = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        // 1. Explicit ?lang= query parameter
        $queryLang = $request->query('lang');
        if (is_string($queryLang) && in_array($queryLang, self::SUPPORTED_LOCALES, true)) {
            return $queryLang;
        }

        // 2. Accept-Language header
        $headerLocale = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);
        if ($headerLocale !== null) {
            return $headerLocale;
        }

        // 3. Authenticated user's preferred_locale
        $user = $request->user();
        if ($user !== null) {
            $preferred = $user->preferred_locale ?? null;
            if (is_string($preferred) && in_array($preferred, self::SUPPORTED_LOCALES, true)) {
                return $preferred;
            }
        }

        return self::DEFAULT_LOCALE;
    }
}
