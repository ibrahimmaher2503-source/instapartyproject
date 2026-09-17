<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\ChatIdentityController;
use App\Modules\Communication\Http\Controllers\ChatTokenController;
use App\Modules\Communication\Http\Controllers\NotificationPreferenceController;
use App\Modules\Communication\Http\Controllers\VendorChatThreadController;
use App\Modules\Communication\Http\Controllers\VendorNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:vendor')->group(function () {
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'indexVendor']);
    Route::put('/notification-preferences/{channel}/{event_category}', [NotificationPreferenceController::class, 'updateVendor']);

    Route::middleware('vendor.approved')->group(function (): void {
        // Chat threads mirror (vendor-portal 11.1; send/read-state are
        // Firestore-first per spec 056 — intentionally no REST endpoints).
        Route::get('/chat/threads', VendorChatThreadController::class);

        // Chat identity bridge (spec 056): /identity feeds the web-portal proxy
        // (parity with /customer/chat/identity); /token mints the Firebase custom
        // token directly for mobile apps, which have no Next.js proxy. GET so the
        // suspension read-only gate keeps chat visible for suspended vendors.
        Route::get('/chat/identity', ChatIdentityController::class);
        Route::get('/chat/token', ChatTokenController::class);
    });

    // In-app notifications inbox (vendor-portal 17.1–17.5 / G11).
    Route::get('/notifications', [VendorNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [VendorNotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{publicId}/mark-read', [VendorNotificationController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [VendorNotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{publicId}', [VendorNotificationController::class, 'destroy']);
});
