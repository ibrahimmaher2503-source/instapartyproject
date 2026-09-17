<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Controllers\Web\CartController;
use Illuminate\Support\Facades\Route;

Route::get('cart', CartController::class)->name('cart');
Route::middleware(['auth:sanctum', 'role:customer'])->group(function (): void {
    Route::post('cart/setup', [CartController::class, 'setup'])->name('cart.setup');
    Route::delete('cart/{bookingPublicId}/items/{itemPublicId}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('cart/{bookingPublicId}/submit', [CartController::class, 'submit'])->name('cart.submit');
});
