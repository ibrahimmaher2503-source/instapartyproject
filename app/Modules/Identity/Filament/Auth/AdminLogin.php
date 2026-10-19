<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Auth;

use App\Modules\Identity\Application\Actions\VerifyAdminMfaAction;
use App\Modules\Identity\Domain\Models\TwoFactorSecret;
use App\Modules\Identity\Domain\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;

final class AdminLogin extends IsolatedPanelLogin
{
    protected function getForms(): array
    {
        return ['form' => $this->form($this->makeForm()->schema([
            $this->getEmailFormComponent(), $this->getPasswordFormComponent(),
            TextInput::make('mfa_code')->label(__('identity::security.code'))
                ->helperText(__('identity::security.login_hint'))->maxLength(32)
                ->autocomplete('one-time-code')->extraInputAttributes(['dir' => 'ltr']),
        ])->statePath('data'))];
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();
            return null;
        }
        $data = $this->form->getState();
        $guard = Filament::auth();
        $credentials = $this->getCredentialsFromFormData($data);
        $provider = $guard->getProvider();
        $user = $provider->retrieveByCredentials($credentials);
        if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)
            || $user->status !== 'active' || ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            $this->throwFailureValidationException();
        }
        $mfa = TwoFactorSecret::where('user_id', $user->id)->whereNotNull('confirmed_at')->first();
        if ($mfa !== null && ! app(VerifyAdminMfaAction::class)->execute($user, (string) ($data['mfa_code'] ?? ''))) {
            $this->throwFailureValidationException();
        }
        // Persistent login cookies must never bypass the second factor.
        $guard->login($user, false);
        session()->regenerate();
        session()->forget('admin_mfa_verified');
        if ($mfa !== null) {
            session()->put('admin_mfa_verified', $user->id.':'.hash('sha256', $mfa->secret_encrypted));
        }
        unset($this->data['password'], $this->data['mfa_code']);

        return app(LoginResponse::class);
    }
}
