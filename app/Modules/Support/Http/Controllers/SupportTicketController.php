<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Controllers;

use App\Modules\Shared\Http\ApiResponse;
use App\Modules\Support\Application\Actions\SubmitSupportTicketAction;
use App\Modules\Support\Application\DTOs\SubmitSupportTicketDTO;
use App\Modules\Support\Http\Requests\SubmitSupportTicketRequest;
use App\Modules\Support\Http\Resources\SupportTicketConfirmationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * @group Support
 */
class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SubmitSupportTicketAction $action,
    ) {}

    public function store(SubmitSupportTicketRequest $request): JsonResponse
    {
        $dto = new SubmitSupportTicketDTO(
            subject: $request->string('subject')->toString(),
            body: $request->string('body')->toString(),
            bookingPublicId: $request->input('booking_id'),
            userId: auth('sanctum')->id(),
            email: $request->input('email'),
        );

        $ticket = $this->action->execute($dto);

        $statusCode = $ticket->wasRecentlyCreated ? 201 : 200;

        return ApiResponse::success(new SupportTicketConfirmationResource($ticket), [], $statusCode);
    }
}
