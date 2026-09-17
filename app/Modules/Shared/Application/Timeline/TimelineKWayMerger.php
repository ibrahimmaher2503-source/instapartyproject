<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline;

use App\Modules\Shared\Application\Timeline\DTOs\TimelineEntryDTO;

/**
 * Bounded-heap k-way merger for per-source TimelineEntryDTO streams.
 *
 * Algorithm: PHP-side min-heap merge ordered by (occurredAt ASC, canonicalRank ASC).
 * Each source stream provides exactly (pageSize + 1) pre-fetched rows.
 * The merger outputs at most $limit rows; the (limit+1)th row signals hasMore = true.
 *
 * Deduplication: when two rows share the same (occurredAt, subject implicit),
 * the row with the lower canonicalRank wins. The higher-ranked duplicate is dropped.
 */
final class TimelineKWayMerger
{
    /**
     * @param  array<int, array<int, array{dto: TimelineEntryDTO, rank: int}>>  $streams
     *                                                                                    Each stream is an array of ['dto' => TimelineEntryDTO, 'rank' => int] items.
     * @return array{entries: list<TimelineEntryDTO>, hasMore: bool}
     */
    public static function merge(array $streams, int $limit): array
    {
        // Flatten all stream items into one list
        $all = [];
        foreach ($streams as $stream) {
            foreach ($stream as $item) {
                $all[] = $item;
            }
        }

        // Sort by (occurredAt ASC, canonicalRank ASC)
        usort($all, static function (array $a, array $b): int {
            $cmp = $a['dto']->occurredAt->getTimestamp() <=> $b['dto']->occurredAt->getTimestamp();
            if ($cmp !== 0) {
                return $cmp;
            }

            // Tie-break: lower canonicalRank (more specific) wins
            return $a['rank'] <=> $b['rank'];
        });

        // Deduplication: drop higher-ranked entries with the same (occurredAt, sourceTable, sourcePublicId)
        $seen = [];
        $merged = [];
        $hasMore = false;

        foreach ($all as $item) {
            /** @var TimelineEntryDTO $dto */
            $dto = $item['dto'];

            $dedupeKey = $dto->occurredAt->getTimestamp().'|'.$dto->sourceTable.'|'.($dto->sourcePublicId ?? '');

            if (isset($seen[$dedupeKey])) {
                continue; // Already included a lower-rank entry for this event
            }

            if (count($merged) >= $limit) {
                $hasMore = true;
                break;
            }

            $seen[$dedupeKey] = true;
            $merged[] = $dto;
        }

        return ['entries' => $merged, 'hasMore' => $hasMore];
    }
}
