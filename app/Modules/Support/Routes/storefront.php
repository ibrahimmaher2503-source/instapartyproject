<?php

declare(strict_types=1);

use App\Modules\Support\Http\Controllers\Web\CustomerHelpController;
use Illuminate\Support\Facades\Route;

Route::get('help', CustomerHelpController::class)->name('support.help');
Route::get('faq', fn () => redirect()->route('storefront.pages.show', ['locale' => app()->getLocale(), 'slug' => 'faq']))
    ->name('support.faq');
Route::post('help/tickets', [CustomerHelpController::class, 'store'])->name('support.tickets.store');
