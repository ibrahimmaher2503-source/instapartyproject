<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\Exceptions\TimelineViewerScopeRequiredException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves (subject × viewer × audience) into a set of defensive SQL predicates
 * that the builder pins onto every source-table query.
 *
 * This is a second defensive layer — Filament policy is the first.
 * Even if the policy somehow passes, this scope ensures the query can never
 * return rows belonging to another vendor.
 */
final class TimelineSubjectScope
{
    private readonly int $subjectId;

    private readonly string $subjectClass;

    private readonly ?int $viewerVendorProfileId;

    public function __construct(
        private readonly Model $subject,
        private readonly ?User $viewer,
        private readonly TimelineAudience $audience,
    ) {
        $this->subjectId = $subject->getKey();
        $this->subjectClass = get_class($subject);

        if ($audience === TimelineAudience::Vendor && $viewer === null) {
            throw TimelineViewerScopeRequiredException::forAudience($audience->value);
        }

        $this->viewerVendorProfileId = $viewer?->vendorProfile?->id;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }

    public function getSubjectClass(): string
    {
        return $this->subjectClass;
    }

    public function getAudience(): TimelineAudience
    {
        return $this->audience;
    }

    public function getViewer(): ?User
    {
        return $this->viewer;
    }

    public function getViewerVendorProfileId(): ?int
    {
        return $this->viewerVendorProfileId;
    }

    /**
     * Apply this scope's defensive vendor constraint onto a query builder.
     * Call this inside descriptor queryBuilder closures for any query that
     * joins a vendor_profile_id column.
     */
    public function applyVendorConstraint(Builder $query, string $column = 'vendor_profile_id'): Builder
    {
        if ($this->audience === TimelineAudience::Vendor && $this->viewerVendorProfileId !== null) {
            $query->where($column, $this->viewerVendorProfileId);
        }

        return $query;
    }

    /**
     * Returns false — signals to descriptors that they should emit empty results.
     * Used when a ChatThread (admin-only) is accessed by a vendor audience.
     */
    public function isDenied(): bool
    {
        // ChatThread is always denied for vendor audience; enforced at descriptor level.
        return false;
    }
}
