<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationFinding extends Model
{
    public $timestamps = false;

    protected $table = 'reconciliation_findings';

    protected $fillable = [
        'public_id',
        'reconciliation_run_id',
        'finding_type',
        'severity',
        'resource_type',
        'resource_id',
        'expected',
        'actual',
        'delta',
        'resolution',
        'resolved_by_user_id',
        'resolved_at',
        'description_key',
        'description_params',
        'created_at',
        'open_condition_key',
        'last_seen_at',
        'last_seen_run_id',
        'resolution_note',
        'assigned_to_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'finding_type' => ReconciliationFindingType::class,
            'severity' => ReconciliationFindingSeverity::class,
            'expected' => 'array',
            'actual' => 'array',
            'delta' => 'array',
            'description_params' => 'array',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_seen_run_id' => 'integer',
            'assigned_to_admin_id' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_admin_id');
    }

    public function scopeCanonicalConditions(Builder $query): Builder
    {
        return $query->whereNull('reconciliation_findings.resolution')
            ->whereNull('reconciliation_findings.resolved_at')
            ->where(function (Builder $current): void {
                $current->whereNotNull('reconciliation_findings.open_condition_key')
                    ->orWhereNotExists(function ($other): void {
                        $other->selectRaw('1')->from('reconciliation_findings as canonical')
                            ->whereColumn('canonical.finding_type', 'reconciliation_findings.finding_type')
                            ->whereRaw('COALESCE(canonical.resource_type, ?) = COALESCE(reconciliation_findings.resource_type, ?)', ['__NULL__', '__NULL__'])
                            ->whereRaw('COALESCE(canonical.resource_id, ?) = COALESCE(reconciliation_findings.resource_id, ?)', [-1, -1])
                            ->whereRaw('COALESCE(canonical.description_key, ?) = COALESCE(reconciliation_findings.description_key, ?)', ['__NULL__', '__NULL__'])
                            ->where(function ($preferred): void {
                                $preferred->whereNotNull('canonical.open_condition_key')
                                    ->orWhere(fn ($legacy) => $legacy
                                        ->whereNull('canonical.open_condition_key')
                                        ->whereNull('canonical.resolution')
                                        ->whereNull('canonical.resolved_at')
                                        ->whereColumn('canonical.id', '<', 'reconciliation_findings.id'));
                            });
                    });
            });
    }
}
