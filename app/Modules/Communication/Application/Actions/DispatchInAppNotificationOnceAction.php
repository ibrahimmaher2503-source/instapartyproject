<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DispatchInAppNotificationOnceAction
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function execute(DispatchNotificationDTO $dto, ?string $eventIdentity = null): ?NotificationDispatch
    {
        if ($dto->channel !== NotificationChannel::InApp || $dto->referenceId === null || $dto->referenceType === null) {
            throw new InvalidArgumentException('An in-app event requires a durable reference.');
        }

        $eventIdentity ??= $dto->context['event_identity'] ?? null;

        return DB::transaction(function () use ($dto, $eventIdentity): ?NotificationDispatch {
            // Lock a row that exists before the first dispatch, including concurrent retries.
            if (DB::table('users')->where('id', $dto->userId)->lockForUpdate()->first(['id']) === null) {
                return null;
            }

            $existing = NotificationDispatch::query()
                ->where('user_id', $dto->userId)
                ->where('channel', NotificationChannel::InApp->value)
                ->where('reference_type', $dto->referenceType)
                ->where('reference_id', $dto->referenceId)
                ->where('context->event_identity', $eventIdentity)
                ->where(fn ($query) => $query
                    ->where(fn ($query) => $query
                        ->where('context->event_key', $dto->eventKey)
                        ->where('context->audience', $dto->audience->value))
                    ->orWhereHas('template', fn ($query) => $query
                        ->where('event_key', $dto->eventKey)
                        ->where('audience', $dto->audience->value)))
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: $dto->eventKey,
                channel: $dto->channel,
                audience: $dto->audience,
                eventCategory: $dto->eventCategory,
                userId: $dto->userId,
                context: array_merge($dto->context, [
                    'event_identity' => $eventIdentity,
                    'event_key' => $dto->eventKey,
                    'audience' => $dto->audience->value,
                ]),
                referenceType: $dto->referenceType,
                referenceId: $dto->referenceId,
                directBody: $dto->directBody,
                directSubject: $dto->directSubject,
            ));
        });
    }
}
