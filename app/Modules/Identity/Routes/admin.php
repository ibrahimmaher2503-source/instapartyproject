<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AdminVendorApprovalController;
use App\Modules\Identity\Http\Controllers\VendorChangeRequestController;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/admin')->middleware(['api', SetLocaleMiddleware::class, 'auth:sanctum', 'role:admin'])->group(function (): void {
    Route::get('vendor-profiles', [AdminVendorApprovalController::class, 'index']);
    Route::post('vendor-profiles/{vendorProfile}/approve', [AdminVendorApprovalController::class, 'approve'])->middleware('permission:approve_vendor_profile');
    Route::post('vendor-profiles/{vendorProfile}/reject', [AdminVendorApprovalController::class, 'reject'])->middleware('permission:reject_vendor_profile');
    Route::post('vendor-profiles/{vendorProfile}/approve-for-type', [AdminVendorApprovalController::class, 'approveForType'])->middleware('permission:approve_vendor_for_type');
    Route::post('vendor-profiles/{vendorProfile}/revoke-type', [AdminVendorApprovalController::class, 'revokeType'])->middleware('permission:revoke_vendor_type');
    Route::post('vendor-profiles/{vendorProfile}/suspend', [AdminVendorApprovalController::class, 'suspend'])->middleware('permission:suspend_vendor');
    Route::post('vendor-profiles/{vendorProfile}/reactivate', [AdminVendorApprovalController::class, 'reactivate'])->middleware('permission:suspend_vendor');
    Route::post('vendor-profiles/{publicId}/change-requests', [VendorChangeRequestController::class, 'store'])->middleware('permission:manage_vendor_profile');
});
