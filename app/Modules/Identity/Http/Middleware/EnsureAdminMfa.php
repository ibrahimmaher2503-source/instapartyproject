<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Services\AdminMfaSession;
use App\Modules\Identity\Domain\Models\TwoFactorSecret;
use App\Modules\Identity\Filament\Pages\AdminSecurityPage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user !== null && $user->status === 'active', 403);
        $mfa = TwoFactorSecret::where('user_id', $user->id)->whereNotNull('confirmed_at')->first();
        if ($mfa !== null && $request->session()->get('admin_mfa_verified') !== AdminMfaSession::token($user, $mfa->secret_encrypted)) {
            Auth::guard('web')->logout();
            $request->session()->forget(['admin_mfa_verified', 'admin_mfa_pending']);
            $request->session()->regenerate();

            return redirect()->to(filament()->getPanel('admin')->getLoginUrl());
        }
        if ($mfa === null && config('app.admin_mfa_required') && ! $request->routeIs(
            AdminSecurityPage::getRouteName(), 'filament.admin.auth.logout',
        )) {
            return redirect()->to(AdminSecurityPage::getUrl());
        }

        return $next($request);
    }
}
