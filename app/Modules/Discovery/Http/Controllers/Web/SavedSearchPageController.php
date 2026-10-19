<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Discovery\Domain\Models\SavedSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SavedSearchPageController
{
    private const FILTER_KEYS = [
        'q',
        'product_type',
        'category_slug',
        'subcategory',
        'occasion',
        'package',
        'vendor',
        'governorate_public_id',
        'city_public_id',
        'event_date',
        'price_min',
        'price_max',
        'guest_count',
        'min_rating',
        'sort',
    ];

    public function index(Request $request): View
    {
        $savedSearches = SavedSearch::query()
            ->where('user_id', (int) $request->user()->getAuthIdentifier())
            ->latest()
            ->paginate(10);

        return view('storefront.account.saved-searches', compact('savedSearches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'filters' => ['nullable', 'array'],
        ]);

        $filters = collect((array) ($data['filters'] ?? []))
            ->only(self::FILTER_KEYS)
            ->filter(fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();

        SavedSearch::query()->create([
            'user_id' => (int) $request->user()->getAuthIdentifier(),
            'label' => $data['label'],
            'filters' => $filters,
        ]);

        return back()->with('saved_search_status', __('account.saved_search_saved'));
    }

    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless((int) $savedSearch->user_id === (int) $request->user()->getAuthIdentifier(), 404);

        $savedSearch->delete();

        return back()->with('saved_search_status', __('account.saved_search_deleted'));
    }
}
