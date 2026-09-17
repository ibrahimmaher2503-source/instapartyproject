<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Responses;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\RegistrationResponse as BaseRegistrationResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

final class VendorRegistrationResponse extends BaseRegistrationResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        session()->flash('registration_successful', true);
        session()->flash('verification_pending', true);

        session()->put(
            'url.intended',
            Filament::getEmailVerificationPromptUrl() ?? url('/vendor-portal/login'),
        );

        return parent::toResponse($request);
    }
}
