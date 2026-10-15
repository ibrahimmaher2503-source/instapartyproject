<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Web;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Loyalty\Application\Actions\ListCustomerLoyaltyBalancesAction;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CustomerLoyaltyAccountController
{
    public function __invoke(Request $request, ListCustomerLoyaltyBalancesAction $balances): View
    {
        /** @var User $user */
        $user = $request->user();
        $customerBalances = $balances->execute($user->id);
        $history = LoyaltyLedgerEntry::query()
            ->where('user_id', $user->id)
            ->with('vendorProfile')
            ->orderByDesc('id')
            ->paginate(20);

        return view('storefront.account.loyalty', compact('customerBalances', 'history'));
    }
}
