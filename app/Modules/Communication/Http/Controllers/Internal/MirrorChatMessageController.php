<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Internal;

use App\Modules\Communication\Application\Actions\MirrorFirestoreMessageAction;
use App\Modules\Communication\Http\Requests\Internal\MirrorChatMessageRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * @group Internal
 */
class MirrorChatMessageController
{
    public function __construct(
        private readonly MirrorFirestoreMessageAction $action,
    ) {}

    /**
     * Mirror a Firestore chat message into chat_message_log (idempotent).
     *
     * @response 200 {"data":{"chat_message_log_id":123,"created":true},"meta":{},"errors":null}
     */
    public function __invoke(MirrorChatMessageRequest $request): JsonResponse
    {
        try {
            $log = $this->action->execute($request->toDTO());
        } catch (DomainException $e) {
            return response()->json(['data' => null, 'meta' => [], 'errors' => [$e->getMessage()]], 404);
        }

        return response()->json([
            'data' => ['chat_message_log_id' => $log->id, 'created' => $log->wasRecentlyCreated],
            'meta' => [],
            'errors' => null,
        ]);
    }
}
