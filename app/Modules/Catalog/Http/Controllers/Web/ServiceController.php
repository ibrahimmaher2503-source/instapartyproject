<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Application\Actions\ShowPublishedServiceAction;
use Illuminate\Contracts\View\View;

class ServiceController
{
    public function __invoke(string $servicePublicId, ShowPublishedServiceAction $service): View
    {
        return view('storefront.services.show', ['service' => $service->execute($servicePublicId)]);
    }
}
