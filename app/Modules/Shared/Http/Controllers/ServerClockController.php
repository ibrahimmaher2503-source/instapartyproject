<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Feature 054 — US14 (C11). Authoritative server clock so date pickers can compute
 * a `min` date without trusting a possibly-skewed device clock. Public, no auth,
 * never cached.
 *
 * @group Utilities
 */
class ServerClockController
{
    /**
     * @response 200 {
     *   "data": { "now": "2026-05-29T14:32:11+00:00", "timezone": "UTC", "epoch_ms": 1748528531000 },
     *   "meta": {},
     *   "errors": null
     * }
     */
    public function show(): JsonResponse
    {
        $now = Carbon::now('UTC');

        return ApiResponse::success([
            'now' => $now->toIso8601String(),
            'timezone' => config('app.timezone', 'UTC'),
            'epoch_ms' => $now->getTimestampMs(),
        ])->header('Cache-Control', 'no-store');
    }
}
