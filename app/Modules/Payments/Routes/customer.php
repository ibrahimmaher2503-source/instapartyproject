<?php

declare(strict_types=1);

use App\Modules\Payments\Http\Controllers\Customer\InitiatePaymentController;
use App\Modules\Payments\Http\Controllers\Customer\ShowPaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:customer', 'ensure.account.active', 'idempotency'])->group(function (): void {
    Route::post('/api/v1/customer/bookings/{bookingPublicId}/payments', InitiatePaymentController::class)->name('payments.initiate');
});

Route::middleware(['auth:sanctum', 'role:customer'])->group(function (): void {
    Route::get('/api/v1/customer/payments/{paymentPublicId}', ShowPaymentController::class);
});
