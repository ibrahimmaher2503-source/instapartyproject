<?php

declare(strict_types=1);

use App\Modules\Loyalty\Http\Controllers\Web\CustomerLoyaltyAccountController;
use Illuminate\Support\Facades\Route;

Route::get('account/loyalty', CustomerLoyaltyAccountController::class)
    ->middleware(['auth', 'role:customer'])->name('account.loyalty');
