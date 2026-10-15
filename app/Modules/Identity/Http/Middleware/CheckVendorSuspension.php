<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use BackedEnum;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class CheckVendorSuspension
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('vendor')->user();

        $profile = $user?->vendorProfile;

        if ($profile && $this->resolveApprovalStatus($profile) === 'suspended') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('identity.account_suspended'),
                ], 403);
            }

            try {
                $suspensionRoute = route('filament.vendor.pages.account-suspended', [], false);
            } catch (RouteNotFoundException $e) {
                $suspensionRoute = '/vendor/account-suspended';
            }

            // Allow access to the suspension page itself to avoid redirect loops.
            if ($request->is(ltrim($suspensionRoute, '/'))) {
                return $next($request);
            }

            return new RedirectResponse($suspensionRoute);
        }

        return $next($request);
    }

    private function resolveApprovalStatus(mixed $profile): string
    {
        $raw = $profile->approval_status;

        if ($raw instanceof BackedEnum) {
            return $raw->value;
        }

        if (is_object($raw) && method_exists($raw, 'getValue')) {
            return $raw->getValue();
        }

        return (string) $raw;
    }
}
