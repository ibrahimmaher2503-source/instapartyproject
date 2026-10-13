<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Catalog\Application\Actions\ListPublicOccasionsAction;
use App\Modules\Discovery\Application\Actions\StorePlannerContextAction;
use App\Modules\Discovery\Http\Requests\StorePlannerContextRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlannerController
{
    public function store(StorePlannerContextRequest $request, StorePlannerContextAction $action): RedirectResponse
    {
        return redirect()->to($action->execute($request->validated()));
    }

    public function __invoke(Request $request, ListPublicOccasionsAction $occasions): View|RedirectResponse
    {
        $requestedService = $request->query('service');
        $servicePublicId = is_string($requestedService) && $requestedService !== ''
            ? $requestedService
            : null;

        if ($servicePublicId !== null && $request->user() === null) {
            return redirect()->guest(route('storefront.auth.login'));
        }

        return view('storefront.wizard', [
            'bookingMode' => $servicePublicId !== null,
            'occasions' => $occasions->execute($servicePublicId),
            'servicePublicId' => $servicePublicId,
        ]);
    }
}
