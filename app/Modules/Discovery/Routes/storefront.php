<?php

declare(strict_types=1);

use App\Modules\Discovery\Http\Controllers\Web\PackageRecommendationController;
use App\Modules\Discovery\Http\Controllers\Web\PlannerController;
use App\Modules\Discovery\Http\Controllers\Web\SearchController;
use App\Modules\Discovery\Http\Controllers\Web\VendorController;
use Illuminate\Support\Facades\Route;

Route::get('search', SearchController::class)->name('search');
Route::get('wizard', PlannerController::class)->name('wizard');
Route::post('wizard/context', [PlannerController::class, 'store'])->name('wizard.context');
Route::get('packages/{publicId}', PackageRecommendationController::class)->name('packages.show');
Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
// Human-readable slugs are preferred; legacy public ULIDs redirect without changing API identity.
Route::get('vendors/{publicId}', [VendorController::class, 'show'])->name('vendors.show');
