<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Discovery\Domain\Models\SavedSearch;
use App\Modules\Geography\Domain\Contracts\GeographyRepository;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CustomerSavedSearchController
{
    /** @var list<string> */
    private const FILTER_KEYS = [
        'q', 'planner', 'product_type', 'product_types', 'category_slug', 'occasion', 'package', 'vendor',
        'governorate_public_id', 'city_public_id', 'event_date', 'event_starts_at',
        'event_ends_at', 'price_min', 'price_max', 'guest_count', 'min_rating', 'sort',
    ];

    public function index(Request $request): View
    {
        $savedSearches = SavedSearch::query()
            ->where('user_id', $this->user($request)->id)
            ->latest('id')
            ->paginate(15);

        return view('storefront.account.saved-searches', compact('savedSearches'));
    }

    public function store(Request $request, GeographyRepository $geography): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'filters' => ['nullable', 'array'],
        ]);

        $rawFilters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        $filters = array_intersect_key($rawFilters, array_flip(self::FILTER_KEYS));
        $validatedFilters = Validator::make($filters, $this->filterRules(! empty($filters['planner'])))->validate();
        if (isset($validatedFilters['city_public_id'], $validatedFilters['governorate_public_id'])) {
            $city = $geography->findCityByPublicId((string) $validatedFilters['city_public_id']);
            $governorate = $geography->findGovernorateByPublicId((string) $validatedFilters['governorate_public_id']);
            if ($city === null || $governorate === null || $city->governorate_id !== $governorate->id) {
                throw ValidationException::withMessages(['filters.city_public_id' => __('storefront.search.validation.invalid_filters')]);
            }
        }
        $normalizedFilters = $this->normalize($validatedFilters);

        SavedSearch::query()->create([
            'user_id' => $this->user($request)->id,
            'label' => trim($data['label']),
            'filters' => $normalizedFilters,
        ]);

        return to_route('storefront.account.saved-searches.index', ['locale' => app()->getLocale()])
            ->with('status', __('discovery.saved_search.saved'));
    }

    public function destroy(Request $request, int $savedSearch): RedirectResponse
    {
        SavedSearch::query()
            ->where('user_id', $this->user($request)->id)
            ->whereKey($savedSearch)
            ->firstOrFail()
            ->delete();

        return to_route('storefront.account.saved-searches.index', ['locale' => app()->getLocale()])
            ->with('status', __('discovery.saved_search.deleted'));
    }

    /** @return array<string, array<int, mixed>> */
    private function filterRules(bool $planner): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'planner' => ['nullable', 'boolean'],
            'product_type' => ['nullable', Rule::enum(ProductType::class)],
            'product_types' => ['nullable', 'array', 'list', 'size:1'],
            'product_types.*' => [Rule::enum(ProductType::class)],
            'category_slug' => ['nullable', 'string', 'max:120'],
            'occasion' => [$planner ? 'required' : 'nullable', 'string', 'max:120'],
            'package' => ['nullable', 'string', 'max:26'],
            'vendor' => ['nullable', 'string', 'max:26'],
            'governorate_public_id' => [$planner ? 'required' : 'required_with:city_public_id', 'nullable', 'string', 'max:26', 'exists:governorates,public_id'],
            'city_public_id' => [$planner ? 'required' : 'nullable', 'string', 'max:26', 'exists:cities,public_id'],
            'event_date' => ['nullable', 'date_format:Y-m-d', ...($planner ? [] : ['after_or_equal:today'])],
            'event_starts_at' => [$planner ? 'required' : 'nullable', 'date', 'after:now'],
            'event_ends_at' => ['nullable', 'date', 'after:event_starts_at'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0', 'gte:price_min'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'min_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc', 'rating_desc', 'newest'])],
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalize(array $filters): array
    {
        $normalized = [];

        foreach (self::FILTER_KEYS as $key) {
            if (! array_key_exists($key, $filters) || $key === 'page') {
                continue;
            }

            $value = $filters[$key];
            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }
            }

            $normalized[$key] = match ($key) {
                'planner' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'price_min', 'price_max', 'guest_count' => (int) $value,
                'min_rating' => (float) $value,
                default => $value,
            };
        }

        if (! isset($normalized['product_type']) && isset($normalized['product_types'])) {
            $normalized['product_type'] = $normalized['product_types'][0];
        }
        unset($normalized['product_types']);

        return $normalized;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
