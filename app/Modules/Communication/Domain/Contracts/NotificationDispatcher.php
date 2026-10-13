<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Models\NotificationDispatch;

interface NotificationDispatcher
{
    public function execute(DispatchNotificationDTO $dto): ?NotificationDispatch;

    public function dispatch(
        string $eventKey,
        int $userId,
        NotificationAudience $audience,
        array $context = [],
        ?string $referenceType = null,
        ?int $referenceId = null
    ): void;
}
