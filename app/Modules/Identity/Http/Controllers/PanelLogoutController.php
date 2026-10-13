<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse;

final class PanelLogoutController
{
    public function __invoke(): LogoutResponse
    {
        Filament::auth()->logout();

        // Keep other panel guards in the shared session while rotating its ID and CSRF token.
        request()->session()->regenerate(true);

        return app(LogoutResponse::class);
    }
}
