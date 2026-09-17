<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Services\VendorSearchQuery;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class SearchVendorProfilesAction
{
    public function __construct(private readonly VendorSearchQuery $searchQuery) {}

    /**
     * @param  array{status?: string, search?: string, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, VendorProfile>
     */
    public function execute(User $actor, array $filters = []): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', VendorProfile::class);

        $status = filled($filters['status'] ?? null) ? (string) $filters['status'] : null;
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = VendorProfile::query()
            ->when(filled($status), fn (Builder $query) => $query->where('approval_status', $status));

        $this->searchQuery->apply($query, $search);

        return $query
            ->with(['user', 'primaryGovernorate', 'primaryCity', 'approvedTypes'])
            ->latest()
            ->paginate(min(max($perPage, 1), 100));
    }
}
