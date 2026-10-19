<?php

declare(strict_types=1);

use App\Modules\Discovery\Http\Controllers\Web\PackageRecommendationController;
use App\Modules\Discovery\Http\Controllers\Web\PlannerController;
use App\Modules\Discovery\Http\Controllers\Web\SavedSearchPageController;
use App\Modules\Discovery\Http\Controllers\Web\SearchController;
use App\Modules\Discovery\Http\Controllers\Web\VendorController;
use Illuminate\Support\Facades\Route;

Route::get('search', SearchController::class)->name('search');
Route::middleware(['auth', 'role:customer'])->group(function (): void {
    Route::get('account/saved-searches', [SavedSearchPageController::class, 'index'])
        ->name('account.saved-searches');
    Route::post('account/saved-searches', [SavedSearchPageController::class, 'store'])
        ->name('account.saved-searches.store');
    Route::delete('account/saved-searches/{savedSearch}', [SavedSearchPageController::class, 'destroy'])
        ->name('account.saved-searches.destroy');
});
Route::get('wizard', PlannerController::class)->name('wizard');
Route::post('wizard/context', [PlannerController::class, 'store'])->name('wizard.context');
Route::get('packages/{publicId}', PackageRecommendationController::class)->name('packages.show');
Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
// Human-readable slugs are preferred; legacy public ULIDs redirect without changing API identity.
Route::get('vendors/{publicId}', [VendorController::class, 'show'])->name('vendors.show');
