<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Controllers\Admin\BookingInvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'locale', 'auth:web', 'role:admin|super_admin|booking_manager'])
    ->prefix('admin/bookings')
    ->name('admin.bookings.')
    ->group(function (): void {
        Route::get('{bookingPublicId}/invoice', BookingInvoiceController::class)->name('invoice');
    });
