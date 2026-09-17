<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Events\VendorAccountDeleted;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vendor self-service account deletion (live audit 2026-06-06 §12.2).
 *
 * Refuses while the vendor has active obligations (open bookings, wallet
 * funds, open withdrawals) — those need fulfilment or admin settlement
 * first. Otherwise: soft-deletes profile + user (both on the CLAUDE.md §14
 * soft-delete list), revokes every Sanctum token, writes the audit row, and
 * lets Catalog archive the listings via VendorAccountDeleted.
 */
class DeleteVendorAccountAction
{
    public function execute(User $user, string $currentPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('identity.invalid_current_password'),
            ]);
        }

        $vendor = $user->vendorProfile()->firstOrFail();

        $this->assertNoActiveObligations($vendor);

        DB::transaction(function () use ($user, $vendor): void {
            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => VendorProfile::class,
                'auditable_id' => $vendor->id,
                'user_id' => $user->id,
                'action' => 'vendor_account_deleted',
                'changes' => json_encode(['initiated_by' => 'vendor']),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);

            $vendor->delete();
            $user->tokens()->delete(); // revoke every session/token (policy: deletion kills access)
            $user->delete();

            DB::afterCommit(fn () => event(new VendorAccountDeleted($vendor->id, $user->id)));
        });
    }

    /**
     * Cross-module guard reads use query-builder table reads (no model
     * imports across module boundaries — same precedent as the services
     * subquery in Reviews).
     */
    private function assertNoActiveObligations(VendorProfile $vendor): void
    {
        $hasOpenBookings = DB::table('booking_vendors')
            ->where('vendor_profile_id', $vendor->id)
            ->whereIn('sub_status', ['pending', 'accepted', 'modified', 'in_progress'])
            ->exists();

        if ($hasOpenBookings) {
            throw ValidationException::withMessages([
                'account' => __('identity.account_deletion.open_bookings'),
            ]);
        }

        $hasWalletFunds = DB::table('wallets')
            ->where('owner_type', VendorProfile::class)
            ->where('owner_id', $vendor->id)
            ->where(fn ($query) => $query
                ->where('balance_minor', '>', 0)
                ->orWhere('pending_withdrawal_minor', '>', 0))
            ->exists();

        if ($hasWalletFunds) {
            throw ValidationException::withMessages([
                'account' => __('identity.account_deletion.wallet_balance'),
            ]);
        }

        $hasOpenWithdrawals = DB::table('withdrawals')
            ->where('vendor_profile_id', $vendor->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($hasOpenWithdrawals) {
            throw ValidationException::withMessages([
                'account' => __('identity.account_deletion.open_withdrawals'),
            ]);
        }
    }
}
