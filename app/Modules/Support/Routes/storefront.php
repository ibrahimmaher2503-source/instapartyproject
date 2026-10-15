<?php

declare(strict_types=1);

use App\Modules\Support\Http\Controllers\Web\CustomerHelpController;
use Illuminate\Support\Facades\Route;

Route::get('help', CustomerHelpController::class)->name('support.help');
Route::get('faq', CustomerHelpController::class)->name('support.faq');
Route::post('help/tickets', [CustomerHelpController::class, 'store'])->name('support.tickets.store');
