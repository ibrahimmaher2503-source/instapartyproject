<?php

declare(strict_types=1);

use App\Modules\Promotions\Http\Controllers\PromoCodeController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/customer')
    ->middleware(['api', 'auth:sanctum', 'role:customer'])
    ->group(function () {
        Route::post('promo-codes/validate', [PromoCodeController::class, 'validate']);
        Route::get('promotions/active', [PromoCodeController::class, 'listActive']);
    });
