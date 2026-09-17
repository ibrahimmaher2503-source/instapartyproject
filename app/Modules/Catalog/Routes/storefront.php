<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\Web\ServiceController;
use App\Modules\Catalog\Http\Controllers\Web\TaxonomyController;
use Illuminate\Support\Facades\Route;

Route::get('services/{servicePublicId}', ServiceController::class)->name('services.show');
Route::get('c/{identifier}', [TaxonomyController::class, 'category'])->name('categories.show');
Route::get('o/{identifier}', [TaxonomyController::class, 'occasion'])->name('occasions.show');
