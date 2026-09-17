<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\Internal\MirrorChatMessageController;
use Illuminate\Support\Facades\Route;

// Service-to-service (Cloud Function → backend). Secret-guarded; no Sanctum session.
Route::post('/chat/mirror-message', MirrorChatMessageController::class);
