<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Shared\Domain\Concerns\AppendOnly;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InternalRecordNote extends Model
{
    use AppendOnly;
    use HasPublicId;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = ['author_id', 'kind', 'body'];

    /** @return array<int, class-string<Model>> */
    public static function allowedSubjectClasses(): array
    {
        return [
            'App\\Modules\\Booking\\Domain\\Models\\Booking',
            'App\\Modules\\Identity\\Domain\\Models\\User',
            'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
            'App\\Modules\\Support\\Domain\\Models\\SupportTicket',
            'App\\Modules\\TrustSafety\\Domain\\Models\\Report',
        ];
    }

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo((string) config('auth.providers.users.model'), 'author_id');
    }
}
