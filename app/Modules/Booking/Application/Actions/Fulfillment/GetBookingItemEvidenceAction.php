<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment;

use App\Modules\Booking\Domain\Models\BookingFulfillmentIssue;
use App\Modules\Booking\Domain\Models\BookingItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class GetBookingItemEvidenceAction
{
    public const TTL_SECONDS = 300;

    /**
     * Returns signed-URL objects for every media item in the given collection.
     *
     * @return array<int, array{public_id: string, url: string, expires_at: string, mime_type: string}>
     */
    public function execute(
        BookingItem|BookingFulfillmentIssue $model,
        string $collection,
        int $accessorUserId,
        string $ip,
        ?string $userAgent,
    ): array {
        $mediaItems = $model->getMedia($collection);
        $expiry = now()->addSeconds(self::TTL_SECONDS);
        $disk = Storage::disk('s3-private');
        $results = [];

        foreach ($mediaItems as $media) {
            $url = method_exists($disk, 'temporaryUrl')
                ? $disk->temporaryUrl($media->getPathRelativeToRoot(), $expiry)
                : $disk->url($media->getPathRelativeToRoot());

            $results[] = [
                'public_id' => $media->public_id,
                'url' => $url,
                'expires_at' => $expiry->toISOString(),
                'mime_type' => $media->mime_type,
            ];
        }

        if (count($results) > 0) {
            $auditRows = array_map(fn (array $r) => [
                'public_id' => (string) Str::ulid(),
                'auditable_type' => $model::class,
                'auditable_id' => $model->id,
                'user_id' => $accessorUserId,
                'action' => 'private_media.accessed',
                'changes' => json_encode([
                    'media_public_id' => $r['public_id'],
                    'collection' => $collection,
                    'disk' => 's3-private',
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'ttl_seconds' => self::TTL_SECONDS,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ], $results);

            DB::table('audit_logs')->insert($auditRows);
        }

        return $results;
    }
}
