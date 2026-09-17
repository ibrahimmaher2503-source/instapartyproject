<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Auth;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login;

class IsolatedPanelLogin extends Login
{
    public function mount(): void
    {
        $guard = Filament::auth();
        $user = $guard->user();
        $panel = Filament::getCurrentPanel();

        if ($user instanceof FilamentUser && ! $user->canAccessPanel($panel)) {
            $guard->logout();
            request()->session()->regenerateToken();
        }

        parent::mount();
    }
}
