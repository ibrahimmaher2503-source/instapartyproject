<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Discovery\Infrastructure\Repositories\VendorBrowsingRepository;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Shared\Application\Services\PublicMediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController
{
    public function index(Request $request, VendorReviewRepository $reviews): View
    {
        $search = trim((string) $request->query('q', ''));
        $search = mb_substr($search, 0, 100);
        $sort = $request->query('sort') === 'newest' ? 'newest' : 'rating';
        $vendors = VendorProfile::query()
            ->with(['primaryCity.governorate', 'primaryGovernorate', 'coverageAreas.city.governorate'])
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
        VendorReviewRepository $reviews,
        Request $request,
    ): View|RedirectResponse {
        $vendor = VendorProfile::query()
            ->with(['primaryCity.governorate', 'primaryGovernorate', 'coverageAreas.city.governorate'])
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

        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $searchQuery = trim($filters['q'] ?? '');
        $selectedType = filled($filters['type'] ?? null) ? ProductType::from($filters['type']) : null;
        abort_if($selectedType === ProductType::Digital && ! config('catalog.digital_services_enabled', true), 404);
        $services = $repository->servicesFor($vendor->id, $selectedType, 15, $searchQuery)->withQueryString();
        $reviewSummary = $reviews->publicSummariesForVendors([$vendor->id])[$vendor->id];

        return view('storefront.vendors.show', [
            'vendor' => $vendor,
            'reviewSummary' => $reviewSummary,
            'services' => $services,
            'selectedType' => $selectedType,
            'searchQuery' => $searchQuery,
            'serviceNames' => $repository->serviceNamesFor($vendor->id, app()->getLocale()),
            'serviceTypeCounts' => $repository->serviceTypeCountsFor($vendor->id),
            'galleryItems' => collect($repository->portfolioFor($vendor->id, PHP_INT_MAX)['items']),
            'coverImageUrl' => app(PublicMediaUrl::class)->publicPath($vendor->cover_path),
            'logoUrl' => app(PublicMediaUrl::class)->publicPath($vendor->logo_path),
        ]);
    }
}
