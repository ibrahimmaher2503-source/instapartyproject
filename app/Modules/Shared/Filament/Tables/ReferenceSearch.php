<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ReferenceSearch
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, list<string>>  $fields  Relationship name => searchable fields; an empty name means the current record.
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, string $search, array $fields): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        // Keep every OR inside the existing table's permission, state and filter scope.
        return $query->where(function (Builder $match) use ($fields, $pattern): void {
            foreach ($fields as $relationship => $columns) {
                $predicate = function (Builder $related) use ($columns, $pattern): void {
                    $related->where(function (Builder $values) use ($columns, $pattern): void {
                        foreach ($columns as $column) {
                            $wrapped = $values->getQuery()->getGrammar()->wrap($values->getModel()->qualifyColumn($column));
                            $values->orWhereRaw($wrapped." LIKE ? ESCAPE '!'", [$pattern]);
                        }
                    });
                };

                if ($relationship === '') {
                    $match->orWhere($predicate);
                } else {
                    $match->orWhereHas($relationship, $predicate);
                }
            }
        });
    }
}
