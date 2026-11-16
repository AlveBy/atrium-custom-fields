<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields;

use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Query\CustomQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Facet counts for a filter panel: how many records of the query have each
 * value of a field. One GROUP BY on the typed index per field.
 *
 *     $facets = CustomFacets::for(Product::class, Product::query()->where('active', true))
 *         ->filters(['color' => ['red'], 'price' => ['min' => 10, 'max' => 50]]);
 *
 *     $facets->counts(['color', 'size']);  // ['color' => ['red' => 12, 'blue' => 3], 'size' => [...]]
 *     $facets->ranges(['price']);          // ['price' => ['min' => 9.9, 'max' => 120.0]]
 *
 * With filters(), each field is counted with every filter but its own, so
 * the panel still offers "blue" while "red" is ticked; the records
 * themselves are listed with whereCustomFilters() and the same array.
 */
final class CustomFacets
{
    /** @var array<string,mixed> */
    private array $filters = [];

    private function __construct(private readonly Builder $query) {}

    /**
     * @param  class-string<Model>|Model  $model
     */
    public static function for(Model|string $model, ?Builder $query = null): self
    {
        $model = is_string($model) ? new $model : $model;

        return new self($query ?? $model->newQuery());
    }

    /**
     * @param  array<string,mixed>  $filters  code => value | list | ['min' => …, 'max' => …]
     */
    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @param  array<int,string>  $codes
     * @return array<string,array<string|int,int>> code => value => count, most frequent first
     */
    public function counts(array $codes): array
    {
        $counts = [];

        foreach ($codes as $code) {
            $field = $this->fields()->fieldOrFail($this->query->getModel(), $code);
            $counts[$code] = $this->count($field);
        }

        return $counts;
    }

    /**
     * @param  array<int,string>  $codes
     * @return array<string,array{min:float|int|string|null,max:float|int|string|null}>
     */
    public function ranges(array $codes): array
    {
        $ranges = [];

        foreach ($codes as $code) {
            $field = $this->fields()->fieldOrFail($this->query->getModel(), $code);
            $base = $this->base($field);

            if ($field->column !== null) {
                $column = $base->qualifyColumn($field->column);
                $row = $base->toBase()->reorder()->selectRaw('min('.$this->wrap($column).') as min_value, max('.$this->wrap($column).') as max_value')->first();
            } else {
                $this->assertIndexed($field);
                $column = $field->type->indexColumn();
                $row = $this->queries()->index($field)
                    ->whereIn('entity_id', $this->ids($base))
                    ->reorder()
                    ->select(DB::raw('min('.$column.') as min_value, max('.$column.') as max_value'))
                    ->first();
            }

            $ranges[$code] = [
                // An aggregate without a group: always one row.
                'min' => $this->cast($field, $row->min_value),
                'max' => $this->cast($field, $row->max_value),
            ];
        }

        return $ranges;
    }

    /**
     * @return array<string|int,int>
     */
    private function count(CustomField $field): array
    {
        $base = $this->base($field);

        if ($field->column !== null) {
            $column = $base->qualifyColumn($field->column);
            $rows = $base->toBase()->reorder()
                ->whereNotNull($column)
                ->selectRaw($this->wrap($column).' as facet_value, count(*) as facet_count')
                ->groupBy($column)
                ->get();
        } else {
            $this->assertIndexed($field);
            $column = $field->type->indexColumn();
            $rows = $this->queries()->index($field)
                ->reorder()
                ->select($column.' as facet_value', DB::raw('count(*) as facet_count'))
                ->whereIn('entity_id', $this->ids($base))
                ->groupBy($column)
                ->get();
        }

        $counts = [];

        foreach ($rows as $row) {
            $value = $this->cast($field, $row->facet_value);

            if ($value !== null) {
                // A yes/no is keyed 1 or 0, as PHP keys every bool.
                $counts[$value] = (int) $row->facet_count;
            }
        }

        // Most frequent first, equal counts by value: the same order every time.
        ksort($counts);
        arsort($counts);

        return $counts;
    }

    /**
     * The query with every filter but the field's own.
     */
    private function base(CustomField $field): Builder
    {
        $base = clone $this->query;

        return $this->queries()->filters($base, $this->filters, $field->code);
    }

    /**
     * The ids of the query, as a subquery: no order, no columns of its own.
     */
    private function ids(Builder $query): \Illuminate\Database\Query\Builder
    {
        return $query->toBase()->reorder()->select($query->getModel()->getQualifiedKeyName());
    }

    private function cast(CustomField $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            FieldType::Number, FieldType::Link => (int) $value,
            FieldType::Decimal => (float) $value,
            // "1", 1 or "1.000000" (a decimal column read as text).
            FieldType::Boolean => (float) $value === 1.0,
            // Text, an option or a date ("2026-11-17"): kept as text everywhere.
            default => $value,
        };
    }

    private function assertIndexed(CustomField $field): void
    {
        if (! $field->isIndexed()) {
            throw new \LogicException("Custom field [{$field->code}] has no facets: tick \"Filterable\" on it.");
        }
    }

    private function wrap(string $column): string
    {
        return $this->query->getQuery()->getGrammar()->wrap($column);
    }

    private function fields(): CustomFields
    {
        return app(CustomFields::class);
    }

    private function queries(): CustomQuery
    {
        return app(CustomQuery::class);
    }
}
