<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Modules\Communication\Domain\Contracts\ChatTokenMinter;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mints a Firebase custom token for the authenticated user so mobile apps
 * (no Next.js proxy in their path) can call signInWithCustomToken and read
 * their Firestore chat threads directly (spec 056 identity bridge).
 *
 * uid == numeric users.id (string); admins carry a role=admin custom claim.
 * Firebase custom tokens are valid for one hour (fixed by Firebase).
 *
 * @group Chat
 */
class ChatTokenController
{
    public function __construct(
        private readonly ChatTokenMinter $minter,
    ) {}

    /**
     * @response 200 {"data":{"token":"eyJhbGciOi...","user_id":42},"meta":{"expires_in":3600},"errors":null}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success(
            ['token' => $this->minter->mint($user->id, (bool) $user->hasRole('admin')), 'user_id' => $user->id],
            ['expires_in' => 3600],
        );
    }
}
