<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CustomerAddressPageController
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $addresses = CustomerAddress::query()
            ->where('user_id', $user->id)
            ->with('city')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->paginate(10);

        return view('storefront.account.addresses', compact('addresses'));
    }
}
