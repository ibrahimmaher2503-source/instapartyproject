<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Discovery\Infrastructure\Repositories\VendorBrowsingRepository;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VendorController
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $search = mb_substr($search, 0, 100);
        $sort = $request->query('sort') === 'newest' ? 'newest' : 'rating';
        $vendors = VendorProfile::query()
            ->with(['primaryCity', 'approvedTypes'])
            ->withCount(['services' => fn ($query) => $query->published()])
            ->whereState('approval_status', ApprovedState::class)
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search) {
                $match->where('business_name->ar', 'like', "%{$search}%")
                    ->orWhere('business_name->en', 'like', "%{$search}%")
                    ->orWhereHas('primaryCity', fn ($city) => $city->where('name->ar', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%"))
                    ->orWhereHas('services', fn ($service) => $service->published()->where(function ($details) use ($search) {
                        $details->where('name->ar', 'like', "%{$search}%")
                            ->orWhere('name->en', 'like', "%{$search}%")
                            ->orWhereHas('category', fn ($category) => $category->where('name->ar', 'like', "%{$search}%")
                                ->orWhere('name->en', 'like', "%{$search}%"));
                    }));
            }))
            ->when($sort === 'rating', fn ($query) => $query->orderByDesc('rating_avg')->orderByDesc('rating_count'))
            ->orderByDesc('id')
            ->paginate(12)->withQueryString();

        return view('storefront.vendors.index', compact('vendors', 'search', 'sort'));
    }

    public function show(
        string $publicId,
        VendorBrowsingRepository $repository,
    ): View {
        $vendor = VendorProfile::query()
            ->with(['primaryCity', 'primaryGovernorate', 'approvedTypes'])
            ->withCount(['services' => fn ($query) => $query->published()])
            ->whereState('approval_status', ApprovedState::class)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $services = $repository->servicesFor($vendor->id, null, 12);

        return view('storefront.vendors.show', [
            'vendor' => $vendor,
            'services' => $services,
            'coverImageUrl' => $vendor->cover_path ? Storage::disk('public')->url($vendor->cover_path) : null,
            'logoUrl' => $vendor->logo_path ? Storage::disk('public')->url($vendor->logo_path) : null,
        ]);
    }
}
