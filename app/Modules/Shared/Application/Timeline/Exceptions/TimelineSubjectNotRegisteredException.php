<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline\Exceptions;

use RuntimeException;

class TimelineSubjectNotRegisteredException extends RuntimeException
{
    public static function for(string $subjectClass): self
    {
        return new self("Subject class [{$subjectClass}] is not registered in the TimelineSourceRegistry. Register it in your module's ServiceProvider::boot().");
    }
}
