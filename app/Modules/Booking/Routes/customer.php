<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Controllers\Customer\BookingCancellationController;
use App\Modules\Booking\Http\Controllers\Customer\BookingController;
use App\Modules\Booking\Http\Controllers\Customer\BookingItemController;
use App\Modules\Booking\Http\Controllers\Customer\BookingNegotiationController;
use App\Modules\Booking\Http\Controllers\Customer\CheckoutReviewController;
use App\Modules\Booking\Http\Controllers\Customer\TaxInvoiceRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'locale', 'role:customer'])
    ->prefix('api/v1/customer')
    ->group(function (): void {
        Route::get('bookings', [BookingController::class, 'index']);
        Route::get('bookings/current-draft', [BookingController::class, 'currentDraft']);
        Route::get('bookings/{bookingPublicId}', [BookingController::class, 'show']);
        Route::get('bookings/{bookingPublicId}/cancellation-preview', [BookingCancellationController::class, 'preview']);
        Route::get('bookings/{bookingPublicId}/modifications', [BookingNegotiationController::class, 'listModifications']);
        Route::get('bookings/{bookingPublicId}/modifications/{modificationPublicId}', [BookingNegotiationController::class, 'showModification']);

        Route::middleware('ensure.account.active')->group(function (): void {
            Route::post('bookings', [BookingController::class, 'store']);
            Route::delete('bookings/{bookingPublicId}', [BookingController::class, 'discardDraft']);
            Route::post('bookings/{bookingPublicId}/items', [BookingItemController::class, 'store']);
            Route::delete('bookings/{bookingPublicId}/items/{itemPublicId}', [BookingItemController::class, 'destroy']);
            Route::post('bookings/{bookingPublicId}/submit', [BookingNegotiationController::class, 'submit']);
            Route::post('bookings/{bookingPublicId}/checkout-review', CheckoutReviewController::class);
            Route::post('bookings/{bookingPublicId}/cancel', [BookingCancellationController::class, 'cancel'])
                ->middleware('idempotency');
            Route::post('bookings/{bookingPublicId}/modifications/{modificationPublicId}/decide', [BookingNegotiationController::class, 'decideModification'])
                ->middleware('idempotency');
            Route::post('bookings/{bookingPublicId}/request-tax-invoice', TaxInvoiceRequestController::class)
                ->middleware('idempotency');
        });
    });
