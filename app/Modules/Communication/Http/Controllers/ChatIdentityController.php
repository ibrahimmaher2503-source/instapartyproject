<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns the chat identity (numeric users.id) for the authenticated user so the
 * Next.js firebase-token proxy can mint a Firebase custom token whose uid matches
 * chat_threads.customer_id and Firestore participantUserIds (FR-EXT-056-011).
 *
 * Server-to-server only (called by the Next proxy, which holds the bearer token).
 *
 * @group Chat
 */
class ChatIdentityController
{
    /**
     * @response 200 {"data":{"user_id":42,"is_admin":false},"meta":{},"errors":null}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'user_id' => $user?->id,
                'is_admin' => (bool) ($user?->hasRole('admin') ?? false),
            ],
            'meta' => [],
            'errors' => null,
        ]);
    }
}
