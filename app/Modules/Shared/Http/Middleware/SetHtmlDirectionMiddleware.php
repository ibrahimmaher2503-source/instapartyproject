<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetHtmlDirectionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        view()->share('htmlDir', App::isLocale('ar') ? 'rtl' : 'ltr');
        view()->share('htmlLang', App::getLocale());

        return $next($request);
    }
}
