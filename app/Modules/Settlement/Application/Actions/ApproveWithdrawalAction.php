<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Events\WithdrawalApproved;
use App\Modules\Settlement\Domain\Exceptions\InvalidWithdrawalTransitionException;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\ApprovedState;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\PendingState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApproveWithdrawalAction
{
    public function execute(Withdrawal $withdrawal, User $admin): Withdrawal
    {
        // Idempotency: if this withdrawal was already approved within the last 24 h, return early.
        // Checked first so replaying the same request short-circuits before state validation.
        $idempotencyKey = "wd_approve:{$withdrawal->id}";
        $alreadyDone = DB::table('idempotency_keys')
            ->where('key', $idempotencyKey)
            ->where('scope', 'internal')
            ->where('scope_key', 'internal_action')
            ->where('expires_at', '>', now())
            ->exists();

        if ($alreadyDone) {
            return $withdrawal->fresh();
        }

        return DB::transaction(function () use ($withdrawal, $admin, $idempotencyKey): Withdrawal {
            // Use persisted history, not an incoming model's dirty request timestamp.
            $withdrawal = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);
            if ($withdrawal->status instanceof ApprovedState) {
                return $withdrawal;
            }
            if (! ($withdrawal->status instanceof PendingState)) {
                throw new InvalidWithdrawalTransitionException(
                    (string) class_basename($withdrawal->status::class),
                    'Approved',
                );
            }
            // Approval consumes the request-time reserve; it must never create another debit.
            $hasReserve = DB::table('wallet_ledger as reserve')
                ->join('wallets as wallet', 'wallet.id', '=', 'reserve.wallet_id')
                ->join('ledger_transaction_groups as reserve_group', 'reserve_group.id', '=', 'reserve.transaction_group_id')
                ->where('reserve.id', $withdrawal->reserved_ledger_entry_id)
                ->where('wallet.owner_type', 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile')
                ->where('wallet.owner_id', $withdrawal->vendor_profile_id)
                ->where('wallet.currency', $withdrawal->requested_amount_currency)
                ->where('reserve.currency', $withdrawal->requested_amount_currency)
                ->where('reserve.amount_minor', $withdrawal->requested_amount_minor)
                ->where('reserve.direction', 'debit')
                ->where('reserve.entry_type', 'withdrawal_reserve')
                ->where('reserve.related_entity_type', 'withdrawal')
                ->where('reserve.related_entity_id', $withdrawal->id)
                ->whereNotNull('reserve.posted_at')
                ->where('reserve_group.kind', 'withdrawal_reserve')
                ->where('reserve_group.currency', $withdrawal->requested_amount_currency)
                ->whereNotNull('reserve_group.posted_at')
                ->exists();

            if (! $hasReserve) {
                throw ValidationException::withMessages(['withdrawal' => __('settlement.withdrawal.missing_reserve')]);
            }

            $withdrawal->update([
                'status' => ApprovedState::class,
                'approved_at' => now(),
                'approved_by_admin_id' => $admin->id,
                'pending_lock' => null,
            ]);

            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => Withdrawal::class,
                'auditable_id' => $withdrawal->id,
                'user_id' => $admin->id,
                'action' => 'withdrawal_approved',
                'changes' => json_encode([
                    'before' => ['status' => WithdrawalStatus::Pending->value],
                    'after' => ['status' => WithdrawalStatus::Approved->value],
                ]),
                'created_at' => now(),
            ]);

            DB::table('idempotency_keys')->insertOrIgnore([[
                'key' => $idempotencyKey,
                'user_id' => $admin->id,
                'route' => "internal_action:{$idempotencyKey}",
                'request_hash' => hash('sha256', (string) $withdrawal->id),
                'response_status' => 200,
                'response_body' => null,
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
                'scope' => 'internal',
                'scope_key' => 'internal_action',
                'ttl_seconds' => 86400,
                'payload_hash' => hash('sha256', (string) $withdrawal->id),
            ]]);

            DB::afterCommit(fn () => event(new WithdrawalApproved(
                withdrawalId: $withdrawal->id,
                withdrawalPublicId: $withdrawal->public_id,
                vendorProfileId: $withdrawal->vendor_profile_id,
                approvedByAdminId: $admin->id,
            )));

            $withdrawal->refresh();

            return $withdrawal;
        });
    }
}
