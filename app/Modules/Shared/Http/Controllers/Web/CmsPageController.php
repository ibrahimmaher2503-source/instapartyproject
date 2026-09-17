<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers\Web;

use App\Modules\Shared\Application\Actions\GetPublishedCmsPageAction;
use Illuminate\Contracts\View\View;

class CmsPageController
{
    public function __invoke(string $slug, GetPublishedCmsPageAction $action): View
    {
        $cmsPage = $action->execute($slug);
        abort_if($cmsPage === null && ! in_array($slug, ['faq', 'trust'], true), 404);

        return view('storefront.pages.cms', ['slug' => $slug, 'cmsPage' => $cmsPage]);
    }
}
