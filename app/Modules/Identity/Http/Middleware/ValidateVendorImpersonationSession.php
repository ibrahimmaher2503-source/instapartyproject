<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Actions\EndImpersonationAction;
use App\Modules\Identity\Application\Services\AdminMfaSession;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ValidateVendorImpersonationSession
{
    public function handle(Request $request, Closure $next)
    {
        $adminId = $request->session()->get('impersonator_id');

        if ($adminId === null) {
            return $next($request);
        }

        $expiresAt = $request->session()->get('impersonation_expires_at');
        $isExpired = ! is_string($expiresAt);

        if (! $isExpired) {
            try {
                $isExpired = now()->greaterThanOrEqualTo(Carbon::parse($expiresAt));
            } catch (Throwable) {
                $isExpired = true;
            }
        }

        $admin = User::find($adminId);
        $adminIsAuthorized = $admin !== null
            && (int) Auth::guard('web')->id() === (int) $adminId
            && $admin->status === 'active'
            && AdminMfaSession::hasProof($admin)
            && $admin->canAccessPanel(filament()->getPanel('admin'))
            && $admin->hasPermissionTo('impersonate_vendor', 'web');

        $vendorProfile = VendorProfile::query()
            ->with('user')
            ->find($request->session()->get('impersonated_vendor_profile_id'));
        $vendor = $vendorProfile?->user;
        $vendorIsEligible = $vendorProfile !== null
            && $vendor !== null
            && (int) Auth::guard('vendor')->id() === (int) $vendor->id
            && $vendor->hasRole('vendor', 'web')
            && $vendor->status === 'active'
            && $vendor->hasVerifiedEmail()
            && $vendor->phone_verified_at !== null
            && $vendor->canAccessPanel(filament()->getPanel('vendor'))
            && $vendorProfile->approval_status instanceof ApprovedState;

        if (! $isExpired && $adminIsAuthorized && $vendorIsEligible) {
            return $next($request);
        }

        app(EndImpersonationAction::class)->execute($isExpired ? 'expired' : 'access_revoked');

        return redirect()->to(filament()->getPanel('admin')->getUrl());
    }
}
