<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Events;

use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class NotificationDispatchRetried
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly NotificationDispatch $dispatch,
        public readonly int $previousAttemptCount,
        public readonly ?int $adminUserId,
    ) {}
}
