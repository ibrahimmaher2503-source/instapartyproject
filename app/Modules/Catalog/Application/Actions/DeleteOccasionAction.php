<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Booking\Domain\Contracts\BookingOccasionReferenceReader;
use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Discovery\Domain\Contracts\PackageRecommendationOccasionReferenceReader;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteOccasionAction
{
    use RecordsTaxonomyAudit;

    public function __construct(
        private readonly BookingOccasionReferenceReader $bookings,
        private readonly PackageRecommendationOccasionReferenceReader $packageRecommendations,
    ) {}

    public function execute(Occasion $occasion, ?User $actor = null): void
    {
        DB::transaction(function () use ($occasion, $actor): void {
            if ($this->bookings->hasReferencesToOccasion($occasion->getKey())
                || $this->packageRecommendations->hasReferencesToOccasion($occasion->getKey())
                || DB::table('occasion_category')->where('occasion_id', $occasion->getKey())->exists()) {
                throw ValidationException::withMessages(['occasion' => __('catalog.occasion_in_use')]);
            }

            $this->recordAudit($occasion, 'occasion_deleted', $actor, ['code' => $occasion->code]);
            $occasion->delete();
        });
    }
}
