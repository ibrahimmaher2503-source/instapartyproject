<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Review/profile/security routes deliberately do not use this middleware. */
final class EnsureVendorApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $vendor = $request->user()?->vendorProfile;

        $user = $request->user();

        if ($vendor === null
            || ! $vendor->approval_status instanceof ApprovedState
            || ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())) {
            return response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [[
                    'code' => $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()
                        ? 'email_verification_required'
                        : 'vendor_approval_required',
                    'message' => $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()
                        ? __('identity::identity.errors.email_verification_required')
                        : __('identity::identity.errors.vendor_approval_required'),
                ]],
            ], 403);
        }

        return $next($request);
    }
}
