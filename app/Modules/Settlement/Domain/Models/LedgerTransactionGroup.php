<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property TransactionKind $kind
 * @property string $currency
 */
class LedgerTransactionGroup extends Model
{
    public $timestamps = false;

    protected $table = 'ledger_transaction_groups';

    protected $fillable = [
        'public_id',
        'kind',
        'currency',
        'correlation_id',
        'causation_id',
        'idempotency_key',
        'initiated_by_user_id',
        'initiator_type',
        'description_key',
        'description_params',
        'metadata',
        'posted_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TransactionKind::class,
            'description_params' => 'array',
            'metadata' => 'array',
            'posted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return HasMany<WalletLedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class, 'transaction_group_id');
    }

    /** @return BelongsTo<User, $this> */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
}
