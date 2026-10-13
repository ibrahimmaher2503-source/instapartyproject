<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Services\AdminMfaSession;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImpersonateVendorViaWebAction
{
    public function execute(VendorProfile $vendorProfile, User $adminUser, string $reasonEn, string $reasonAr): void
    {
        $adminUser = User::query()->findOrFail($adminUser->getKey());
        Gate::forUser($adminUser)->authorize('impersonate_vendor');

        if (
            (int) Auth::guard('web')->id() !== (int) $adminUser->id
            || $adminUser->status !== 'active'
            || ! $adminUser->canAccessPanel(filament()->getPanel('admin'))
            || ! AdminMfaSession::hasProof($adminUser)
            || Session::has('impersonator_id')
            || Auth::guard('vendor')->check()
        ) {
            throw new AuthorizationException(__('identity.impersonation.not_allowed'));
        }

        $reasonEn = trim($reasonEn);
        $reasonAr = trim($reasonAr);

        if (mb_strlen($reasonEn) < 5 || mb_strlen($reasonAr) < 5 || mb_strlen($reasonEn) > 500 || mb_strlen($reasonAr) > 500) {
            throw ValidationException::withMessages([
                'reason_en' => __('identity.impersonation.reason_required'),
                'reason_ar' => __('identity.impersonation.reason_required'),
            ]);
        }

        $vendorProfile = VendorProfile::query()
            ->with('user')
            ->findOrFail($vendorProfile->getKey());
        $vendor = $vendorProfile->user;

        if (
            $vendor === null
            || ! $vendor->hasRole('vendor')
            || $vendor->status !== 'active'
            || ! $vendor->hasVerifiedEmail()
            || $vendor->phone_verified_at === null
            || ! ($vendorProfile->approval_status instanceof ApprovedState)
            || ! $vendor->canAccessPanel(filament()->getPanel('vendor'))
        ) {
            throw new AuthorizationException(__('identity.impersonation.vendor_not_eligible'));
        }

        $startedAt = now();
        $expiresAt = $startedAt->copy()->addMinutes(30);

        DB::transaction(function () use ($vendorProfile, $adminUser, $reasonEn, $reasonAr, $startedAt, $expiresAt): void {
            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => VendorProfile::class,
                'auditable_id' => $vendorProfile->id,
                'user_id' => $adminUser->id,
                'action' => 'vendor_web_impersonation_started',
                'changes' => json_encode([
                    'method' => 'filament_vendor_guard',
                    'reason_en' => $reasonEn,
                    'reason_ar' => $reasonAr,
                    'started_at' => $startedAt->toIso8601String(),
                    'expires_at' => $expiresAt->toIso8601String(),
                ], JSON_THROW_ON_ERROR),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => $startedAt,
            ]);
        });

        Session::put([
            'impersonator_id' => $adminUser->id,
            'impersonator_name' => $adminUser->name,
            'impersonation_started_at' => $startedAt->toIso8601String(),
            'impersonation_expires_at' => $expiresAt->toIso8601String(),
            'impersonated_vendor_profile_id' => $vendorProfile->id,
            'impersonation_reason_en' => $reasonEn,
            'impersonation_reason_ar' => $reasonAr,
        ]);

        Auth::guard('vendor')->login($vendor);
        Session::regenerate();
    }
}
