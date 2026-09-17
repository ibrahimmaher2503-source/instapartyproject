<?php

declare(strict_types=1);

use App\Modules\Support\Http\Controllers\FaqController;
use App\Modules\Support\Http\Controllers\SupportTicketController;
use Illuminate\Support\Facades\Route;

// Public FAQ routes (no auth required)
Route::prefix('api/v1/customer')->middleware(['api'])->group(function () {
    Route::get('faq/categories', [FaqController::class, 'categories']);
    Route::get('faq/search', [FaqController::class, 'search']);

    // Support tickets open to guests and authenticated users
    Route::post('support/tickets', [SupportTicketController::class, 'store']);
});
