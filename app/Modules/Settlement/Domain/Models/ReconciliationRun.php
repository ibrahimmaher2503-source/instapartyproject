<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRun extends Model
{
    public $timestamps = false;

    protected $table = 'reconciliation_runs';

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected $fillable = [
        'public_id',
        'scope_type',
        'scope_params',
        'status',
        'triggered_by_user_id',
        'trigger_kind',
        'idempotency_key',
        'correlation_id',
        'wallets_scanned',
        'findings_count',
        'auto_repaired_count',
        'manual_review_count',
        'started_at',
        'completed_at',
        'failure_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_params' => 'array',
            'status' => ReconciliationStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ReconciliationFinding::class);
    }

    public function observedFindings(): BelongsToMany
    {
        return $this->belongsToMany(ReconciliationFinding::class, 'reconciliation_finding_runs', 'reconciliation_run_id', 'reconciliation_finding_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
