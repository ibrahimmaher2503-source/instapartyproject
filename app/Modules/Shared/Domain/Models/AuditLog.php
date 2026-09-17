<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Read-only façade over the existing audit_logs table.
 * Writers must use DB::table('audit_logs')->insert([...]) — never Eloquent save/update.
 */
class AuditLog extends Model
{
    use AppendOnly;

    protected $table = 'audit_logs';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [];

    protected $guarded = ['*'];

    protected $with = ['actor:id,name'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'auditable_type', 'auditable_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
