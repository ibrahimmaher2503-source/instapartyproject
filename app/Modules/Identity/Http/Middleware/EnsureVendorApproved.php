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

        if ($vendor === null || ! $vendor->approval_status instanceof ApprovedState) {
            return response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [[
                    'code' => 'vendor_approval_required',
                    'message' => __('identity::identity.errors.vendor_approval_required'),
                ]],
            ], 403);
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [[
                    'code' => 'email_verification_required',
                    'message' => __('identity::identity.errors.email_verification_required'),
                ]],
            ], 403);
        }

        if ($user?->phone_verified_at === null) {
            return response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [[
                    'code' => 'phone_verification_required',
                    'message' => __('identity::identity.errors.phone_verification_required'),
                ]],
            ], 403);
        }

        return $next($request);
    }
}
