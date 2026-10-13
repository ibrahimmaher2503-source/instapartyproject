<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Auth;

use Filament\Forms\Components\Component;
use Filament\Pages\Auth\PasswordReset\ResetPassword;
use Illuminate\Validation\Rules\Password;

final class AdminResetPassword extends ResetPassword
{
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->rule(Password::min(12)->mixedCase()->numbers()->symbols());
    }
}
