<?php

declare(strict_types=1);

use App\Modules\Settlement\Http\Controllers\Customer\CustomerWalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/customer')
    ->middleware(['api', 'auth:sanctum', 'role:customer'])
    ->group(function () {
        Route::get('wallet', [CustomerWalletController::class, 'balance']);
        Route::get('wallet/transactions', [CustomerWalletController::class, 'transactions']);
        Route::get('wallet/refunds', [CustomerWalletController::class, 'refunds']);
    });
