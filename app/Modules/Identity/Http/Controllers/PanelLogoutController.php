<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\EndImpersonationAction;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse;

final class PanelLogoutController
{
    public function __invoke(): LogoutResponse
    {
        if (request()->session()->has('impersonator_id')) {
            app(EndImpersonationAction::class)->execute('logout');
        }
        Filament::auth()->logout();

        // Keep other panel guards in the shared session while rotating its ID and CSRF token.
        request()->session()->regenerate(true);

        return app(LogoutResponse::class);
    }
}
