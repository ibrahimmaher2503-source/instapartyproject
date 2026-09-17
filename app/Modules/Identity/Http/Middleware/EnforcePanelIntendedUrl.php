<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Services\PanelIntendedUrlGuard;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforcePanelIntendedUrl
{
    public function __construct(private readonly PanelIntendedUrlGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->guard->forgetWhenOutsidePanel($request, Filament::getCurrentPanel());

        return $next($request);
    }
}
