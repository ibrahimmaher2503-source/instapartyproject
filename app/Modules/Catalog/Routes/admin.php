<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\Admin\AdminServiceChangeRequestController;
use App\Modules\Catalog\Http\Controllers\Admin\AdminServiceMediaController;
use App\Modules\Catalog\Http\Controllers\DownloadImportTemplateController;
use App\Modules\Catalog\Http\Controllers\ServiceChangeRequestController;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/admin/catalog/import/template/{type}', DownloadImportTemplateController::class)
        ->name('admin.catalog.import.template');
});

Route::prefix('api/v1/admin')->middleware(['api', SetLocaleMiddleware::class, 'auth:sanctum', 'role:admin'])->group(function (): void {
    Route::post('services/{service:public_id}/request-changes', [ServiceChangeRequestController::class, 'store'])
        ->middleware('can:update,service')
        ->name('admin.services.request-changes');

    Route::prefix('service-change-requests')->group(function (): void {
        Route::post('{serviceChangeRequest:public_id}/approve', [AdminServiceChangeRequestController::class, 'approve'])
            ->name('admin.service-change-requests.approve');
        Route::post('{serviceChangeRequest:public_id}/reject', [AdminServiceChangeRequestController::class, 'reject'])
            ->name('admin.service-change-requests.reject');
        Route::post('{serviceChangeRequest:public_id}/request-clarification', [AdminServiceChangeRequestController::class, 'requestClarification'])
            ->name('admin.service-change-requests.request-clarification');
    });

    // Service.gallery media — admin parallel of vendor endpoints. Spec 048-media-collections-phase1 US1.
    Route::prefix('services/{service:public_id}/media')->group(function (): void {
        Route::post('/', [AdminServiceMediaController::class, 'upload'])->name('admin.services.media.upload');
        Route::get('/', [AdminServiceMediaController::class, 'list'])->name('admin.services.media.list');
        Route::patch('order', [AdminServiceMediaController::class, 'reorder'])->name('admin.services.media.reorder');
        Route::delete('{mediaPublicId}', [AdminServiceMediaController::class, 'destroy'])->name('admin.services.media.destroy');
    });
});
