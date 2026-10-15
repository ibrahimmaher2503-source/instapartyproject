<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Infrastructure\Repositories;

use App\Modules\Reviews\Application\DTOs\SubmitReviewData;
use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EloquentVendorReviewRepository implements VendorReviewRepository
{
    public function create(SubmitReviewData $data, int $vendorProfileId, int $bookingVendorId): VendorReview
    {
        return VendorReview::create([
            'public_id' => Str::ulid()->toBase32(),
            'vendor_profile_id' => $vendorProfileId,
            'booking_vendor_id' => $bookingVendorId,
            'user_id' => $data->userId,
            'rating' => $data->rating,
            'body' => $data->body,
            'locale' => $data->locale,
            'moderation_status' => ModerationStatus::Pending->value,
        ]);
    }

    public function findByPublicIdForUser(string $publicId, int $userId): ?VendorReview
    {
        return VendorReview::where('public_id', $publicId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findByBookingVendorId(int $bookingVendorId): ?VendorReview
    {
        return VendorReview::withTrashed()
            ->where('booking_vendor_id', $bookingVendorId)
            ->first();
    }

    public function listApprovedForVendor(int $vendorProfileId, array $options = []): array
    {
        $limit = $options['limit'] ?? 15;
        $cursor = $options['cursor'] ?? null;

        $query = VendorReview::with(['reviewer', 'vendorResponse'])
            ->where('vendor_profile_id', $vendorProfileId)
            ->where('moderation_status', ModerationStatus::Approved->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($cursor !== null) {
            $decoded = json_decode(base64_decode($cursor), true);
            if (isset($decoded['created_at'], $decoded['id'])) {
                $query->where(function ($q) use ($decoded): void {
                    $q->where('created_at', '<', $decoded['created_at'])
                        ->orWhere(function ($q2) use ($decoded): void {
                            $q2->where('created_at', '=', $decoded['created_at'])
                                ->where('id', '<', $decoded['id']);
                        });
                });
            }
        }

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        $items = $items->take($limit);

        $nextCursor = $hasMore
            ? base64_encode(json_encode(['created_at' => $items->last()->created_at->toISOString(), 'id' => $items->last()->id]))
            : null;

        return [
            'items' => $items->all(),
            'next_cursor' => $nextCursor,
            'prev_cursor' => null,
        ];
    }

    public function aggregateApprovedForVendor(int $vendorProfileId): array
    {
        $result = VendorReview::where('vendor_profile_id', $vendorProfileId)
            ->where('moderation_status', ModerationStatus::Approved->value)
            ->selectRaw('AVG(rating) as average, COUNT(*) as count')
            ->first();

        return [
            'average' => round((float) ($result->average ?? 0), 2),
            'count' => (int) ($result->count ?? 0),
        ];
    }

    public function publicSummariesForVendors(array $vendorProfileIds): array
    {
        $vendorProfileIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $vendorProfileIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($vendorProfileIds === []) {
            return [];
        }

        $aggregates = VendorReview::query()
            ->whereIn('vendor_profile_id', $vendorProfileIds)
            ->where('moderation_status', ModerationStatus::Approved->value)
            ->selectRaw('vendor_profile_id, AVG(rating) as average, COUNT(*) as count')
            ->groupBy('vendor_profile_id')
            ->get()
            ->keyBy('vendor_profile_id');

        $rankedReviews = VendorReview::query()
            ->whereIn('vendor_profile_id', $vendorProfileIds)
            ->where('moderation_status', ModerationStatus::Approved->value)
            ->whereNotNull('body')
            ->select(['id', 'vendor_profile_id'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY vendor_profile_id ORDER BY rating DESC, id DESC) AS vendor_review_rank');

        $featuredReviewIds = DB::query()
            ->fromSub($rankedReviews->toBase(), 'ranked_vendor_reviews')
            ->where('vendor_review_rank', 1)
            ->pluck('id');

        $featuredReviews = $featuredReviewIds->isEmpty()
            ? collect()
            : VendorReview::query()
                ->join('users as featured_reviewers', 'featured_reviewers.id', '=', 'vendor_reviews.user_id')
                ->whereKey($featuredReviewIds)
                ->get([
                    'vendor_reviews.vendor_profile_id',
                    'vendor_reviews.rating',
                    'vendor_reviews.body',
                    'vendor_reviews.created_at',
                    'featured_reviewers.name as reviewer_name',
                ])
                ->mapWithKeys(function (VendorReview $review): array {
                    $reviewerName = trim((string) $review->reviewer_name);

                    return [(int) $review->vendor_profile_id => [
                        'rating' => (int) $review->rating,
                        'body' => (string) $review->body,
                        'reviewer_first_name' => $reviewerName === ''
                            ? null
                            : (preg_split('/\s+/', $reviewerName)[0] ?? null),
                        'created_at' => (string) $review->getRawOriginal('created_at'),
                    ]];
                });

        $summaries = [];

        foreach ($vendorProfileIds as $vendorProfileId) {
            $aggregate = $aggregates->get($vendorProfileId);

            $summaries[$vendorProfileId] = [
                'average' => round((float) ($aggregate->average ?? 0), 2),
                'count' => (int) ($aggregate->count ?? 0),
                'featured_review' => $featuredReviews->get($vendorProfileId),
            ];
        }

        return $summaries;
    }

    public function softDelete(VendorReview $review): void
    {
        $review->delete();
    }

    public function transition(VendorReview $review, string $toStatus, int $moderatorId): void
    {
        $review->update([
            'moderation_status' => $toStatus,
            'moderated_by' => $moderatorId,
            'moderated_at' => now(),
        ]);
    }
}
