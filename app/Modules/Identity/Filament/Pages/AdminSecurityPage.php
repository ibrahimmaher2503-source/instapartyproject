<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Pages;

use App\Modules\Identity\Application\Actions\EnableAdminMfaAction;
use App\Modules\Identity\Domain\Models\TwoFactorSecret;
use App\Modules\Identity\Domain\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

final class AdminSecurityPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'identity::filament.pages.admin-security';

    public static function canAccess(): bool
    {
        $user = Auth::guard('web')->user();
        return $user instanceof User && $user->status === 'active' && $user->canAccessPanel(filament()->getPanel('admin'));
    }

    public static function getNavigationLabel(): string { return __('identity::security.title'); }

    public static function getNavigationGroup(): ?string { return __('admin.nav.groups.security'); }

    public function getTitle(): string { return __('identity::security.title'); }

    public function getViewData(): array
    {
        $user = Auth::guard('web')->user();
        $pending = session('admin_mfa_pending');
        $secret = is_array($pending) && ($pending['user_id'] ?? null) === $user?->id
            && ($pending['expires_at'] ?? 0) > time() ? $pending['secret'] : null;
        $uri = $secret === null ? null : 'otpauth://totp/'.rawurlencode('InstaParty:'.$user->email)
            .'?secret='.$secret.'&issuer=InstaParty';
        return [
            'enabled' => TwoFactorSecret::where('user_id', $user?->id)->whereNotNull('confirmed_at')->exists(),
            'secret' => $secret,
            'qr' => $uri === null ? null : (new Writer(new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd)))->writeString($uri),
            'recoveryCodes' => session()->pull('admin_mfa_recovery_codes', []),
            'required' => (bool) config('app.admin_mfa_required'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('startMfa')->label(__('identity::security.start'))
                ->visible(fn (): bool => ! TwoFactorSecret::where('user_id', Auth::guard('web')->id())->whereNotNull('confirmed_at')->exists())
                ->form([TextInput::make('current_password')->label(__('account.current_password'))->password()->required()->currentPassword('web')])
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    session()->put('admin_mfa_pending', ['user_id' => Auth::guard('web')->id(),
                        'secret' => (new Google2FA)->generateSecretKey(), 'expires_at' => time() + 600]);
                }),
            Action::make('confirmMfa')->label(__('identity::security.confirm'))
                ->visible(fn (): bool => session()->has('admin_mfa_pending'))
                ->form([
                    TextInput::make('current_password')->label(__('account.current_password'))->password()->required()->currentPassword('web'),
                    TextInput::make('mfa_code')->label(__('identity::security.code'))->required()->regex('/^[0-9]{6}$/D')->autocomplete('one-time-code'),
                ])->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);
                    $user = Auth::guard('web')->user();
                    $pending = session('admin_mfa_pending');
                    if (! is_array($pending) || $pending['user_id'] !== $user->id || $pending['expires_at'] <= time()) {
                        throw ValidationException::withMessages(['mfa_code' => __('identity::security.expired')]);
                    }
                    $codes = app(EnableAdminMfaAction::class)->execute($user, $pending['secret'], $data['mfa_code'], $data['current_password']);
                    session()->regenerate();
                    session()->put('admin_mfa_verified', $user->id.':'.hash('sha256', $pending['secret']));
                    session()->put('admin_mfa_recovery_codes', $codes);
                    session()->forget('admin_mfa_pending');
                }),
        ];
    }
}
