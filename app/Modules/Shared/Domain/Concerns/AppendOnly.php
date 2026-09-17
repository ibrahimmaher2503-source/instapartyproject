<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Concerns;

use App\Modules\Shared\Domain\Exceptions\AppendOnlyViolationException;

trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::creating(static function (): void {
            // creating is allowed — inserts are fine on append-only tables
        });

        static::updating(static function (): void {
            throw AppendOnlyViolationException::onUpdate(static::class);
        });

        static::deleting(static function (): void {
            throw AppendOnlyViolationException::onDelete(static::class);
        });
    }
}
