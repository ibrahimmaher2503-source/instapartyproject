<?php

declare(strict_types=1);

use App\Modules\Subscriptions\Http\Controllers\Vendor\ShowSubscriptionController;
use Illuminate\Support\Facades\Route;

// 'ability:vendor' was never a registered alias (only 'role' / 'permission'
// are aliased in bootstrap/app.php) — Laravel tried to resolve a class named
// "ability" and 500'd on every hit (vendor-mobile live audit 2026-06-06).
// Aligned with the rest of the vendor surface: api group + role + suspension gate.
Route::middleware(['api', 'auth:sanctum', 'role:vendor', 'vendor.not_suspended', 'vendor.approved'])->prefix('api/v1/vendor')->group(function () {
    Route::get('subscription', ShowSubscriptionController::class);
});
