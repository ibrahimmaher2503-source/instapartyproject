<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Discovery\Infrastructure\Repositories\VendorBrowsingRepository;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Shared\Application\Services\PublicMediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorController
{
    public function index(Request $request, VendorReviewRepository $reviews): View
    {
        $search = trim((string) $request->query('q', ''));
        $search = mb_substr($search, 0, 100);
        $sort = $request->query('sort') === 'newest' ? 'newest' : 'rating';
        $vendors = VendorProfile::query()
            ->with(['primaryCity'])
            ->withCount(['services' => fn ($query) => $query->displayable()])
            ->whereState('approval_status', ApprovedState::class)
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search) {
                $match->where('business_name->ar', 'like', "%{$search}%")
                    ->orWhere('business_name->en', 'like', "%{$search}%")
                    ->orWhereHas('primaryCity', fn ($city) => $city->where('name->ar', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%"))
                    ->orWhereHas('services', fn ($service) => $service->displayable()->where(function ($details) use ($search) {
                        $details->where('name->ar', 'like', "%{$search}%")
                            ->orWhere('name->en', 'like', "%{$search}%")
                            ->orWhereHas('category', fn ($category) => $category->where('name->ar', 'like', "%{$search}%")
                                ->orWhere('name->en', 'like', "%{$search}%"));
                    }));
            }))
            ->when($sort === 'rating', fn ($query) => $query->orderByDesc('rating_avg')->orderByDesc('rating_count'))
            ->orderByDesc('id')
            ->paginate(12)->withQueryString();

        $reviewSummaries = $reviews->publicSummariesForVendors($vendors->getCollection()->modelKeys());

        return view('storefront.vendors.index', compact('vendors', 'search', 'sort', 'reviewSummaries'));
    }

    public function show(
        string $publicId,
        VendorBrowsingRepository $repository,
        Request $request,
        VendorReviewRepository $reviews,
    ): View|RedirectResponse {
        $vendor = VendorProfile::query()
            ->with(['primaryCity', 'primaryGovernorate'])
            ->withCount(['services' => fn ($query) => $query->displayable()])
            ->whereState('approval_status', ApprovedState::class)
            ->where(fn ($query) => $query->where('public_id', $publicId)->orWhere('slug', $publicId))
            ->firstOrFail();

        if ($publicId === $vendor->public_id && filled($vendor->slug)) {
            $url = route('storefront.vendors.show', ['publicId' => $vendor->slug]);
            if ($request->getQueryString()) {
                $url .= '?'.$request->getQueryString();
            }

            return redirect()->to($url, 301);
        }

        $services = $repository->servicesFor($vendor->id, null, 12);
        $reviewSummary = $reviews->publicSummariesForVendors([$vendor->id])[$vendor->id];
        $mediaUrls = app(PublicMediaUrl::class);

        return view('storefront.vendors.show', [
            'vendor' => $vendor,
            'reviewSummary' => $reviewSummary,
            'services' => $services,
            'serviceTypeCounts' => $repository->serviceTypeCountsFor($vendor->id),
            'galleryItems' => $vendor->getMedia('portfolio')->map(fn ($media): array => [
                'url' => $mediaUrls->for($media, 'large'),
                'thumb' => $mediaUrls->for($media, 'thumb'),
            ])->filter(fn (array $item): bool => $item['url'] !== null && $item['thumb'] !== null)->values(),
            'coverImageUrl' => $mediaUrls->publicPath($vendor->cover_path),
            'logoUrl' => $mediaUrls->publicPath($vendor->logo_path),
        ]);
    }
}
