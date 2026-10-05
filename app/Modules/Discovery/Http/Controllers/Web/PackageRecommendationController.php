<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Discovery\Domain\Contracts\PackageRecommendationReader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class PackageRecommendationController
{
    public function __invoke(
        string $publicId,
        PackageRecommendationReader $packages,
    ): View|RedirectResponse {
        $package = $packages->findPublishedByPublicId($publicId, app()->getLocale());

        if ($package === null) {
            return redirect()
                ->route('storefront.search')
                ->with('package_error', __('storefront.package.load_error'));
        }

        return view('storefront.packages.show', ['package' => $package]);
    }
}
