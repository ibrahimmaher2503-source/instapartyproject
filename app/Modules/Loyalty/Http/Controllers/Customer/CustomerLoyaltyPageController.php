<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Customer;

use App\Modules\Loyalty\Application\Actions\ListCustomerLoyaltyBalancesAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CustomerLoyaltyPageController
{
    public function __invoke(Request $request, ListCustomerLoyaltyBalancesAction $action): View
    {
        return view('storefront.account.loyalty', [
            'balances' => $action->execute((int) $request->user()->getAuthIdentifier()),
        ]);
    }
}
