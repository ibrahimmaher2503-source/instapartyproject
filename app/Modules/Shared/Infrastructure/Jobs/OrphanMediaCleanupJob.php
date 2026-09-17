<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Daily cleanup of orphan media rows. Per FR-EXT-MED-004 and research.md §4.
 *
 *  1. Delete media older than 24h with `model_id IS NULL` (orphaned uploads).
 *  2. For `collection_name = 'inline_images'`: delete media whose `public_id`
 *     is NOT referenced anywhere in the `cms_pages.blocks` JSON column.
 *
 * Writes one `audit_logs` row with `action = 'media.orphan_cleanup_completed'`
 * carrying counts (orphans_deleted, cms_unreferenced_deleted, errors).
 */
final class OrphanMediaCleanupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function handle(): void
    {
        $startedAt = now();
        $orphansDeleted = 0;
        $cmsUnreferenced = 0;
        $errors = 0;

        try {
            $orphansDeleted = $this->deletePureOrphans();
        } catch (Throwable $e) {
            $errors++;
            Log::warning('media.orphan_cleanup.pure_orphans_failed', ['error' => $e->getMessage()]);
        }

        try {
            $cmsUnreferenced = $this->deleteUnreferencedCmsImages();
        } catch (Throwable $e) {
            $errors++;
            Log::warning('media.orphan_cleanup.cms_unreferenced_failed', ['error' => $e->getMessage()]);
        }

        DB::table('audit_logs')->insert([
            'public_id' => (string) Str::ulid(),
            'auditable_type' => 'media',
            'auditable_id' => 0,
            'user_id' => null,
            'action' => 'media.orphan_cleanup_completed',
            'changes' => json_encode([
                'orphans_deleted' => $orphansDeleted,
                'cms_unreferenced_deleted' => $cmsUnreferenced,
                'errors' => $errors,
                'started_at' => $startedAt->toIso8601String(),
                'completed_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    private function deletePureOrphans(): int
    {
        $cutoff = now()->subHours(24);

        $count = 0;
        Media::query()
            ->whereNull('model_id')
            ->where('created_at', '<', $cutoff)
            ->chunkById(100, function ($chunk) use (&$count): void {
                foreach ($chunk as $media) {
                    $media->delete();
                    $count++;
                }
            });

        return $count;
    }

    private function deleteUnreferencedCmsImages(): int
    {
        if (! Schema::hasTable('cms_pages')) {
            return 0;
        }

        // Concatenate every page's blocks JSON into one searchable blob.
        $haystack = DB::table('cms_pages')
            ->select('blocks')
            ->get()
            ->map(fn ($row) => (string) ($row->blocks ?? ''))
            ->implode(' ');

        if ($haystack === '') {
            // No CMS content yet — safe to wipe ALL inline_images? No — keep them; only purge once CMS exists.
            return 0;
        }

        $count = 0;
        Media::query()
            ->where('collection_name', 'inline_images')
            ->chunkById(100, function ($chunk) use (&$count, $haystack): void {
                foreach ($chunk as $media) {
                    $publicId = (string) $media->public_id;
                    if ($publicId !== '' && ! str_contains($haystack, $publicId)) {
                        $media->delete();
                        $count++;
                    }
                }
            });

        return $count;
    }
}
