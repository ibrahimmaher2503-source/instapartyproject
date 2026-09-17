<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline\Exceptions;

use RuntimeException;

class TimelineViewerScopeRequiredException extends RuntimeException
{
    public static function forAudience(string $audience): self
    {
        return new self("A viewer (User model) is required when requesting a [{$audience}]-audience timeline. Pass the authenticated user as the \$viewer argument.");
    }
}
