<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Responses;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\RegistrationResponse as BaseRegistrationResponse;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

final class VendorRegistrationResponse extends BaseRegistrationResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        Filament::auth()->logout();
        session()->regenerate();
        session()->forget('url.intended');

        Notification::make()
            ->title(__('identity::identity.messages.registration_request_received_title'))
            ->body(__('identity::identity.messages.registration_request_received_body'))
            ->info()
            ->persistent()
            ->send();

        return redirect(Filament::getLoginUrl());
    }
}
