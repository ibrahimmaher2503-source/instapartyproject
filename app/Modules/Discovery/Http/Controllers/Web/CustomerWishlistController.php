<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Discovery\Application\Actions\AddToWishlistAction;
use App\Modules\Discovery\Application\Actions\RemoveFromWishlistAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CustomerWishlistController
{
    public function store(Request $request, string $servicePublicId, AddToWishlistAction $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $action->execute((int) $user->getAuthIdentifier(), $servicePublicId);

        return to_route('storefront.account.favorites', ['locale' => app()->getLocale()])
            ->with('status', __('discovery.wishlist_added'));
    }

    public function destroy(Request $request, string $servicePublicId, RemoveFromWishlistAction $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $action->execute((int) $user->getAuthIdentifier(), $servicePublicId);

        return back()->with('status', __('discovery.wishlist_removed'));
    }
}
