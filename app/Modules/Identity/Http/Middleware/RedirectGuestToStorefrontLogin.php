<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Shared\Http\Middleware\SetStorefrontLocaleMiddleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RedirectGuestToStorefrontLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check()) {
            $locale = $request->segment(1);
            if (! in_array($locale, SetStorefrontLocaleMiddleware::SUPPORTED_LOCALES, true)) {
                $locale = SetStorefrontLocaleMiddleware::DEFAULT_LOCALE;
            }

            return redirect()->guest(route('storefront.auth.login', ['locale' => $locale]));
        }

        return $next($request);
    }
}
