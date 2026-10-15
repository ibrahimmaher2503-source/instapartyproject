<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocaleMiddleware
{
    private const array SUPPORTED_LOCALES = ['en', 'ar'];

    private const string DEFAULT_LOCALE = 'en';

    public function handle(Request $request, Closure $next)
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
            // The Filament language-switch middleware runs later and prefers the
            // saved session locale. Keep its state aligned with this explicit choice.
            if ($request->hasSession()) {
                $request->session()->put('locale', $queryLang);
            }

            return $queryLang;
        }

        // Keep Filament's language switch selection ahead of the browser language.
        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;

        foreach ([
            $sessionLocale,
            $request->cookie('filament_language_switch_locale'),
        ] as $savedLocale) {
            if (is_string($savedLocale) && in_array($savedLocale, self::SUPPORTED_LOCALES, true)) {
                return $savedLocale;
            }
        }

        // 3. Accept-Language header
        $headerLocale = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);
        if ($headerLocale !== null) {
            return $headerLocale;
        }

        // 4. Authenticated user's preferred_locale
        $user = $request->user();
        if ($user !== null) {
            $preferred = $user->preferred_locale ?? null;
            if (is_string($preferred) && in_array($preferred, self::SUPPORTED_LOCALES, true)) {
                return $preferred;
            }
        }

        // 5. Application default
        return self::DEFAULT_LOCALE;
    }
}
