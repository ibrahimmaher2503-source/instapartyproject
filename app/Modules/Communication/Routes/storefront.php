<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\Web\CustomerConversationInboxController;
use App\Modules\Communication\Http\Controllers\Web\CustomerVendorInquiryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:customer'])->group(function (): void {
    Route::get('account/chats', CustomerConversationInboxController::class)->name('account.chats');
    Route::get('services/{servicePublicId}/inquiries/create', [CustomerVendorInquiryController::class, 'create'])
        ->name('inquiries.create');
    Route::post('services/{servicePublicId}/inquiries', [CustomerVendorInquiryController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('inquiries.store');

    Route::get('account/inquiries', [CustomerVendorInquiryController::class, 'index'])
        ->name('account.inquiries.index');
    Route::get('account/inquiries/{inquiryPublicId}', [CustomerVendorInquiryController::class, 'show'])
        ->name('inquiries.show');
    Route::post('account/inquiries/{inquiryPublicId}/messages', [CustomerVendorInquiryController::class, 'reply'])
        ->middleware('throttle:10,1')
        ->name('inquiries.reply');
});
