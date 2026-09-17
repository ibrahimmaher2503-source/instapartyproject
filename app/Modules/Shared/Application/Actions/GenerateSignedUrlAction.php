<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use App\Modules\Shared\Domain\Contracts\SignedUrlService;
use App\Modules\Shared\Domain\Events\PrivateMediaAccessed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Generates a short-lived signed URL for a private media file and writes ONE
 * `audit_logs` row (`action = 'private_media.accessed'`) BEFORE returning the URL.
 *
 * Audit is synchronous and inside the transaction; `PrivateMediaAccessed` event
 * fires post-commit for downstream consumers (metrics, security monitoring).
 * Per ADR-0047 §3 / research.md §2.
 *
 * Default TTL: 300 seconds. Caller passes accessor user id, IP, and user agent
 * from the HTTP request (Controllers wire these in).
 */
final class GenerateSignedUrlAction
{
    public const DEFAULT_TTL_SECONDS = 300;

    public const IDEMPOTENCY_WINDOW_SECONDS = 60;

    public function __construct(private readonly SignedUrlService $signedUrl) {}

    /**
     * @return array{url: string, expires_at: string, expires_in_seconds: int, document_type: ?string}
     *
     * @throws ModelNotFoundException
     */
    public function execute(
        Model&HasMedia $parent,
        string $collection,
        string $mediaPublicId,
        int $accessorUserId,
        string $ip,
        ?string $userAgent,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
        ?string $idempotencyKey = null,
    ): array {
        $config = MediaCollectionConfig::for($parent::class, $collection);

        if (! $config->isPrivate()) {
            throw new LogicException(sprintf(
                'GenerateSignedUrlAction called on non-private collection [%s::%s].',
                $parent::class,
                $collection,
            ));
        }

        /** @var Media|null $media */
        $media = $parent->media()
            ->where('collection_name', $collection)
            ->where('public_id', $mediaPublicId)
            ->first();

        if ($media === null) {
            throw (new ModelNotFoundException)->setModel(Media::class, [$mediaPublicId]);
        }

        // Idempotency-Key short-circuit (60s window). Same key + same accessor +
        // same media returns the same URL + the same audit row (NO double-write).
        // Per contracts/private-signed-url.md.
        $cacheKey = $this->idempotencyCacheKey($parent, $media, $accessorUserId, $idempotencyKey);
        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        DB::transaction(function () use ($media, $parent, $accessorUserId, $ip, $userAgent, $ttlSeconds, $config): void {
            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => $parent::class,
                'auditable_id' => $parent->getKey(),
                'user_id' => $accessorUserId,
                'action' => 'private_media.accessed',
                'changes' => json_encode([
                    'media_id' => $media->id,
                    'media_public_id' => $media->public_id,
                    'collection' => $config->collection,
                    'disk' => $config->disk,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'ttl_seconds' => $ttlSeconds,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            DB::afterCommit(function () use ($media, $parent, $accessorUserId, $ip, $userAgent, $ttlSeconds): void {
                event(new PrivateMediaAccessed(
                    mediaId: (int) $media->id,
                    mediaPublicId: (string) $media->public_id,
                    owningEntityType: $parent::class,
                    owningEntityId: (int) $parent->getKey(),
                    accessorUserId: $accessorUserId,
                    ip: $ip,
                    userAgent: $userAgent,
                    ttlSeconds: $ttlSeconds,
                ));
            });
        });

        $signed = $this->signedUrl->generate($media->disk, $media->getPathRelativeToRoot(), $ttlSeconds);

        $signed['document_type'] = is_array($media->custom_properties)
            ? ($media->custom_properties['document_type'] ?? null)
            : null;

        if ($cacheKey !== null) {
            Cache::put($cacheKey, $signed, self::IDEMPOTENCY_WINDOW_SECONDS);
        }

        return $signed;
    }

    private function idempotencyCacheKey(Model $parent, Media $media, int $accessorUserId, ?string $idempotencyKey): ?string
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return null;
        }

        return sprintf(
            'media:signed_url:%s:%d:%d:%s',
            sha1($parent::class),
            (int) $media->id,
            $accessorUserId,
            sha1($idempotencyKey),
        );
    }
}
