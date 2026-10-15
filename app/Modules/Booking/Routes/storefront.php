<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Controllers\Web\CartController;
use App\Modules\Booking\Http\Controllers\Web\CustomerAlternativeVendorProposalController;
use App\Modules\Booking\Http\Controllers\Web\CustomerBookingInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('cart', CartController::class)->name('cart');
Route::get('account/bookings/{bookingPublicId}/invoice', CustomerBookingInvoiceController::class)
    ->middleware(['auth', 'role:customer'])
    ->name('account.bookings.invoice');
Route::post('account/bookings/{bookingPublicId}/vendor-proposals/{interventionPublicId}/decide', [CustomerAlternativeVendorProposalController::class, 'decide'])
    ->middleware(['auth', 'role:customer', 'ensure.account.active'])
    ->name('account.bookings.vendor-proposals.decide');
Route::middleware(['auth:sanctum', 'role:customer'])->group(function (): void {
    Route::post('cart/setup', [CartController::class, 'setup'])->name('cart.setup');
    Route::delete('cart/{bookingPublicId}/items/{itemPublicId}', [CartController::class, 'remove'])->name('cart.remove');
    Route::put('cart/{bookingPublicId}/items/{itemPublicId}', [CartController::class, 'updateQuantity'])->name('cart.update');
    Route::post('cart/{bookingPublicId}/submit', [CartController::class, 'submit'])->name('cart.submit');
});
