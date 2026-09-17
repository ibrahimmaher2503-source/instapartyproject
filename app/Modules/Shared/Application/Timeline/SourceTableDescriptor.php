<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline;

use App\Modules\Shared\Application\Timeline\DTOs\TimelineEntryDTO;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineFilters;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\Enums\TimelineEventKind;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Describes how to query one audit source table for one registered subject class.
 *
 * visibilityRules shape per audience:
 * [
 *   'admin'  => ['columns' => [...], 'jsonKeys' => ['*'], 'stripDeep' => [], 'adminOnly' => false],
 *   'vendor' => ['columns' => [...], 'jsonKeys' => [...], 'stripDeep' => [...], 'adminOnly' => false],
 * ]
 *
 * adminOnly: true causes the entire descriptor to be omitted for non-Admin audiences.
 * canonicalRank: lower = more specific; wins dedup when two sources emit same (occurred_at, subject_id).
 */
final readonly class SourceTableDescriptor
{
    /**
     * @param  Closure(object $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder|\Illuminate\Database\Query\Builder  $queryBuilder
     * @param  Closure(object $row, TimelineAudience $audience): TimelineEntryDTO  $rowMapper
     * @param  array<string, array{columns: list<string>, jsonKeys: list<string>, stripDeep: list<string>, adminOnly: bool}>  $visibilityRules
     */
    public function __construct(
        public string $sourceTable,
        public TimelineEventKind $defaultEventKind,
        public int $canonicalRank,
        public Closure $queryBuilder,
        public Closure $rowMapper,
        public array $visibilityRules,
        // Underlying table column the action uses for cursor WHERE and ORDER BY.
        // MySQL aliases work in ORDER BY but NOT in WHERE — so this must be a real
        // column name, not the projected `... as occurred_at` alias.
        public string $timestampColumn = 'created_at',
    ) {}
}
