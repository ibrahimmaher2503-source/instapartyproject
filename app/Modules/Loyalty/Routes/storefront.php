<?php

declare(strict_types=1);

use App\Modules\Loyalty\Http\Controllers\Customer\CustomerLoyaltyPageController;
use Illuminate\Support\Facades\Route;

Route::get('account/loyalty', CustomerLoyaltyPageController::class)
    ->middleware(['auth', 'role:customer'])->name('account.loyalty');
