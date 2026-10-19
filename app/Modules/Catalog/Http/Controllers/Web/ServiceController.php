<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Application\Actions\BuildServiceEstimateAction;
use App\Modules\Catalog\Http\Requests\ServiceEstimateRequest;
use Illuminate\Contracts\View\View;

class ServiceController
{
    public function __invoke(string $servicePublicId, ServiceEstimateRequest $request, BuildServiceEstimateAction $estimate): View
    {
        return view('storefront.services.show', $estimate->execute($servicePublicId, $request->validated()));
    }
}
