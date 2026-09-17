<?php

declare(strict_types=1);

use App\Modules\TrustSafety\Http\Controllers\EscrowBannerController;
use App\Modules\TrustSafety\Http\Controllers\ReportController;
use App\Modules\TrustSafety\Http\Controllers\VendorBadgeController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::prefix('api/v1/customer')
    ->middleware(['api'])
    ->group(function () {
        Route::get('vendors/{vendorPublicId}/badges', [VendorBadgeController::class, 'show']);
        Route::get('settings/escrow-banner', [EscrowBannerController::class, 'show']);
    });

// Authenticated customer routes
Route::prefix('api/v1/customer')
    ->middleware(['api', 'auth:sanctum', 'role:customer'])
    ->group(function () {
        Route::post('reports', [ReportController::class, 'store']);
    });
