<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Query;

use Atrium\Admin\Support\ContentLanguages;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\FieldType;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Models\CustomField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * What the query macros do (registered by the service provider on every
 * Eloquent builder):
 *
 *     Product::query()
 *         ->whereCustom('color', 'red')               // = , or IN for a list
 *         ->whereCustom('weight', '<=', 5)
 *         ->whereCustomBetween('price', 10, 50)
 *         ->whereCustomFilters(['size' => ['s', 'm'], 'price' => ['min' => 10]])
 *         ->orderByCustom('rating', 'desc');
 *
 * A filtered or sorted field is looked up in the typed index (an indexed
 * `id IN (SELECT entity_id …)`); a promoted one is the column itself. A field
 * that is neither cannot be filtered: tick "Filterable" on it.
 */
final class CustomQuery
{
    private const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'like'];

    private int $joins = 0;

    public function __construct(private readonly CustomFields $fields) {}

    public function where(Builder $query, string $code, string $operator, mixed $value, string $boolean = 'and'): Builder
    {
        $operator = strtolower($operator);

        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Unsupported operator [{$operator}] for custom field [{$code}].");
        }

        $field = $this->filterable($query, $code);
        $negate = $operator === '!=' || $operator === '<>';
        $operator = $negate ? '=' : $operator;

        if ($field->column !== null) {
            $column = $query->qualifyColumn($field->column);

            return $query->where(function (Builder $where) use ($column, $field, $operator, $value, $negate): void {
                if (is_array($value)) {
                    $where->whereIn($column, $this->values($field, $value), 'and', $negate);
                } else {
                    $where->where($column, $negate ? '!=' : $operator, $this->value($field, $value));
                }

                // A record without a value is not that value either, as in the index.
                if ($negate) {
                    $where->orWhereNull($column);
                }
            }, null, null, $boolean);
        }

        $ids = $this->index($field)->where(function (QueryBuilder $index) use ($field, $operator, $value): void {
            $column = $field->type->indexColumn();

            if (is_array($value)) {
                $index->whereIn($column, $this->values($field, $value));
            } else {
                $index->where($column, $operator, $this->value($field, $value));
            }
        });

        return $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids, $boolean, $negate);
    }

    public function between(Builder $query, string $code, mixed $min, mixed $max, string $boolean = 'and'): Builder
    {
        $field = $this->filterable($query, $code);

        if ($field->column !== null) {
            $column = $query->qualifyColumn($field->column);

            return $query->where(function (Builder $range) use ($column, $field, $min, $max): void {
                $this->range($range, $column, $field, $min, $max);
            }, null, null, $boolean);
        }

        $ids = $this->index($field)->where(function (QueryBuilder $index) use ($field, $min, $max): void {
            $this->range($index, (string) $field->type->indexColumn(), $field, $min, $max);
        });

        return $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids, $boolean);
    }

    /**
     * Several filters at once, the shape a filter panel sends:
     * a value (=), a list (any of), or ['min' => …, 'max' => …].
     *
     * @param  array<string,mixed>  $filters  code => value
     */
    public function filters(Builder $query, array $filters, ?string $except = null): Builder
    {
        foreach ($filters as $code => $value) {
            if ($code === $except || $value === null || $value === '' || $value === []) {
                continue;
            }

            if (is_array($value) && (array_key_exists('min', $value) || array_key_exists('max', $value))) {
                $this->between($query, (string) $code, $value['min'] ?? null, $value['max'] ?? null);
            } else {
                $this->where($query, (string) $code, '=', $value);
            }
        }

        return $query;
    }

    /**
     * Records without a value go last in both directions.
     */
    public function orderBy(Builder $query, string $code, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $field = $this->fields->fieldOrFail($query->getModel(), $code);

        if ($field->column !== null) {
            $column = $query->qualifyColumn($field->column);

            return $query->orderByRaw($this->nullsLast($query, $column))->orderBy($column, $direction);
        }

        if (! $field->isIndexed() || ! $field->type->canBeSortable()) {
            throw new LogicException("Custom field [{$code}] cannot be sorted by: tick \"Sortable\" on it.");
        }

        $model = $query->getModel();
        $alias = 'cf_sort_'.$field->id.'_'.(++$this->joins);
        [$site, $locale] = $this->scope($field);

        if ($query->getQuery()->columns === null) {
            $query->select($model->getTable().'.*');
        }

        $query->leftJoin(Indexer::TABLE.' as '.$alias, static function (JoinClause $join) use ($alias, $model, $field, $site, $locale): void {
            $join->on($alias.'.entity_id', '=', $model->getQualifiedKeyName())
                ->where($alias.'.field_id', '=', $field->id)
                ->where($alias.'.site_id', '=', $site)
                ->where($alias.'.locale', '=', $locale);
        });

        $column = $alias.'.'.$field->type->indexColumn();

        return $query->orderByRaw($this->nullsLast($query, $column))->orderBy($column, $direction);
    }

    /**
     * An `or where` matching the text in a searchable field, in any language.
     */
    public function search(Builder $query, CustomField $field, string $search): Builder
    {
        $grammar = $query->getQuery()->getGrammar();
        $like = '%'.mb_strtolower($search).'%';
        $table = $query->getModel()->getTable();

        if ($field->column !== null) {
            return $query->orWhereRaw('lower('.$grammar->wrap($table.'.'.$field->column).') like ?', [$like]);
        }

        $path = $table.'.'.$this->fields->column().'->'.$field->code;
        $paths = $field->translatable
            ? array_map(static fn (string $locale): string => $path.'->'.$locale, $this->locales())
            : [$path];

        foreach ($paths as $selector) {
            $query->orWhereRaw('lower('.$grammar->wrap($selector).') like ?', [$like]);
        }

        return $query;
    }

    /**
     * The entity ids of one field in the current site and language.
     */
    public function index(CustomField $field): QueryBuilder
    {
        [$site, $locale] = $this->scope($field);

        return DB::table(Indexer::TABLE)
            ->select('entity_id')
            ->where('field_id', $field->id)
            ->where('site_id', $site)
            ->where('locale', $locale);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function scope(CustomField $field): array
    {
        return [
            $field->per_site ? $this->fields->site() : 0,
            $field->translatable ? $this->fields->locale() : '',
        ];
    }

    /**
     * A value as the index column holds it.
     */
    private function value(CustomField $field, mixed $value): mixed
    {
        // The index holds a list of several as one option per row. A yes/no
        // is bound as 1 or 0 by the connection.
        $type = $field->type === FieldType::MultiSelect ? FieldType::Select : $field->type;

        return $type->normalize($value);
    }

    /**
     * @param  array<int|string,mixed>  $values
     * @return array<int|string,mixed>
     */
    private function values(CustomField $field, array $values): array
    {
        // What is no value is left out: in a "not in" list it would match
        // nothing at all.
        return array_filter(
            array_map(fn (mixed $value): mixed => $this->value($field, $value), $values),
            static fn (mixed $value): bool => $value !== null,
        );
    }

    private function range(Builder|QueryBuilder $query, string $column, CustomField $field, mixed $min, mixed $max): void
    {
        if ($min !== null && $min !== '') {
            $query->where($column, '>=', $this->value($field, $min));
        }

        if ($max !== null && $max !== '') {
            $query->where($column, '<=', $this->value($field, $max));
        }
    }

    private function filterable(Builder $query, string $code): CustomField
    {
        $field = $this->fields->fieldOrFail($query->getModel(), $code);

        if ($field->column === null && ! $field->isIndexed()) {
            throw new LogicException("Custom field [{$code}] cannot be filtered by: tick \"Filterable\" on it.");
        }

        return $field;
    }

    private function nullsLast(Builder $query, string $column): string
    {
        return 'case when '.$query->getQuery()->getGrammar()->wrap($column).' is null then 1 else 0 end';
    }

    /**
     * @return array<int,string>
     */
    private function locales(): array
    {
        // Never empty: without languages there is the app's locale.
        return app(ContentLanguages::class)->codes();
    }
}
