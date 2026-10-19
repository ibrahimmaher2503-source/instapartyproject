<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Concerns;

use App\Modules\Shared\Domain\Models\InternalRecordNote;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasInternalRecordNotes
{
    public function internalNotes(): MorphMany
    {
        return $this->morphMany(InternalRecordNote::class, 'subject')->latest('created_at');
    }
}
