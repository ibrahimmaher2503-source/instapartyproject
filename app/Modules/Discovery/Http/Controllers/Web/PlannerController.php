<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Catalog\Application\Actions\ListPublicOccasionsAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlannerController
{
    public function __invoke(Request $request, ListPublicOccasionsAction $occasions): View|RedirectResponse
    {
        if ($request->filled('service') && $request->user() === null) {
            return redirect()->guest(route('storefront.auth.login'));
        }

        return view('storefront.wizard', ['occasions' => $occasions->execute()]);
    }
}
