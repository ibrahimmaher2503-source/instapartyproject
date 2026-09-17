<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Responses;

use App\Modules\Identity\Application\Services\PanelIntendedUrlGuard;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\LoginResponse as BaseLoginResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

final class PanelLoginResponse extends BaseLoginResponse
{
    public function __construct(private readonly PanelIntendedUrlGuard $guard) {}

    public function toResponse($request): RedirectResponse|Redirector
    {
        $this->guard->forgetWhenOutsidePanel($request, Filament::getCurrentPanel());

        return parent::toResponse($request);
    }
}
