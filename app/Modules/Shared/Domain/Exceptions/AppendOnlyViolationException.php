<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

use RuntimeException;

class AppendOnlyViolationException extends RuntimeException
{
    public static function onUpdate(string $modelClass): self
    {
        return new self("Cannot update append-only model [{$modelClass}]. This table is write-once. Use DB::table()->insert() for new rows.");
    }

    public static function onDelete(string $modelClass): self
    {
        return new self("Cannot delete from append-only model [{$modelClass}]. This table is write-once.");
    }
}
