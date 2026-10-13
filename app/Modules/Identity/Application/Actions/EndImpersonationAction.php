<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Services\AdminMfaSession;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class EndImpersonationAction
{
    public function execute(string $reason = 'manual'): ?User
    {
        $adminId = Session::get('impersonator_id');

        if ($adminId === null) {
            return null;
        }

        $vendorProfileId = Session::get('impersonated_vendor_profile_id')
            ?? Auth::guard('vendor')->user()?->vendorProfile?->id
            ?? Auth::guard('web')->user()?->vendorProfile?->id;
        $audit = [
            'started_at' => Session::get('impersonation_started_at'),
            'expires_at' => Session::get('impersonation_expires_at'),
            'reason' => in_array($reason, ['manual', 'expired', 'access_revoked', 'logout'], true) ? $reason : 'manual',
            'ended_at' => now()->toIso8601String(),
        ];
        $admin = User::find($adminId);
        $keepAdminSession = $admin !== null
            && (int) Auth::guard('web')->id() === (int) $adminId
            && $admin->status === 'active'
            && AdminMfaSession::hasProof($admin)
            && $admin->canAccessPanel(filament()->getPanel('admin'))
            && $admin->hasPermissionTo('impersonate_vendor', 'web');

        Auth::guard('vendor')->logout();
        Session::forget([
            'impersonator_id',
            'impersonator_name',
            'impersonation_started_at',
            'impersonation_expires_at',
            'impersonated_vendor_profile_id',
            'impersonation_reason_en',
            'impersonation_reason_ar',
        ]);

        if (! $keepAdminSession) {
            Auth::guard('web')->logout();
            Session::forget(['admin_mfa_verified', 'admin_mfa_pending', 'admin_mfa_recovery_codes']);
        }

        Session::regenerate();

        if ($vendorProfileId !== null) {
            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => VendorProfile::class,
                'auditable_id' => $vendorProfileId,
                'user_id' => $admin?->id,
                'action' => match ($audit['reason']) {
                    'expired' => 'vendor_web_impersonation_expired',
                    'access_revoked' => 'vendor_web_impersonation_access_revoked',
                    default => 'vendor_web_impersonation_ended',
                },
                'changes' => json_encode($audit, JSON_THROW_ON_ERROR),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);
        }

        return $keepAdminSession ? $admin : null;
    }
}
