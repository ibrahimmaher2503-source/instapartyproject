<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Actions;

use App\Modules\Reviews\Domain\Contracts\ReviewModerationLogRepository;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ModerateReviewResponseAction
{
    public function __construct(private readonly ReviewModerationLogRepository $logRepository) {}

    public function execute(
        ReviewResponse $response,
        ModerationStatus $toStatus,
        ?array $reason = null,
    ): ReviewResponse {
        $moderatorId = auth()->id();

        if ($moderatorId === null) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($response, $toStatus, $reason, $moderatorId): ReviewResponse {
            $current = ReviewResponse::query()->lockForUpdate()->findOrFail($response->getKey());
            Gate::authorize('moderate', $current);

            $previousStatus = $current->moderation_status;

            if (! $previousStatus->canTransitionTo($toStatus)) {
                throw new InvalidArgumentException(
                    "Cannot transition response from '{$previousStatus->value}' to '{$toStatus->value}'."
                );
            }

            $current->moderation_status = $toStatus;
            $current->save();

            $this->logRepository->append(
                reviewType: $current->review_type->value.'_response',
                reviewId: $current->id,
                fromStatus: $previousStatus->value,
                toStatus: $toStatus->value,
                moderatorId: (int) $moderatorId,
                reason: $reason,
            );

            return $current->fresh();
        });
    }
}
