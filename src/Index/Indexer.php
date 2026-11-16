<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Index;

use Atrium\Admin\Models\Language;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\FieldType;
use Atrium\CustomFields\Models\CustomField;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The typed index: one row per value of a filtered or sorted field, so that
 * a filter is an indexed lookup and a facet a GROUP BY, whatever the
 * database. A list value gives a row per item, a per-site value a row per
 * site, a translatable one a row per language (with the default language's
 * text where a translation is missing).
 *
 * Kept in step on every save and delete of a record; rebuilt by
 * `atrium:custom-fields:reindex`.
 */
final class Indexer
{
    public const TABLE = 'custom_field_index';

    private const CHUNK = 500;

    /** @var array<int,string>|null the languages, read once per sync or rebuild */
    private ?array $locales = null;

    public function __construct(private readonly CustomFields $fields) {}

    /**
     * Rewrite the rows of one record.
     */
    public function sync(Model $record): void
    {
        $this->locales = null;
        $indexed = $this->indexedFields($record);

        if ($indexed === []) {
            return;
        }

        $this->connection()->transaction(function () use ($record, $indexed): void {
            $this->table()
                ->where('entity_type', $record->getMorphClass())
                ->where('entity_id', $record->getKey())
                ->whereIn('field_id', array_map(static fn (CustomField $field): int => $field->id, $indexed))
                ->delete();

            $this->insert($this->rows($record, $this->plan($indexed)));
        });
    }

    public function forget(Model $record): void
    {
        $this->table()
            ->where('entity_type', $record->getMorphClass())
            ->where('entity_id', $record->getKey())
            ->delete();
    }

    public function forgetField(int $fieldId): void
    {
        $this->table()->where('field_id', $fieldId)->delete();
    }

    /**
     * Rebuild the rows of a module, or of one of its fields. Returns the
     * number of records read.
     *
     * @param  class-string<Model>  $model
     */
    public function reindex(string $model, ?CustomField $only = null): int
    {
        $fields = array_filter(
            $this->fields->all($model),
            static fn (CustomField $field): bool => $only === null || $field->id === $only->id,
        );

        $ids = array_map(static fn (CustomField $field): int => $field->id, $fields);
        $this->locales = null;

        if ($ids !== []) {
            $this->table()->whereIn('field_id', $ids)->delete();
        }

        $indexed = array_filter($fields, static fn (CustomField $field): bool => $field->isIndexed());

        if ($indexed === []) {
            return 0;
        }

        $instance = new $model;
        $column = $this->fields->column();
        $plan = $this->plan($indexed);
        $count = 0;

        // Read the bare rows: no global scopes (sites), no model events.
        $instance->newQueryWithoutScopes()
            ->select([$instance->getKeyName(), $column])
            ->whereNotNull($column)
            ->chunkById(self::CHUNK, function ($records) use ($plan, &$count): void {
                $rows = [];

                foreach ($records as $record) {
                    array_push($rows, ...$this->rows($record, $plan));
                }

                $this->insert($rows);
                $count += count($records);
            });

        return $count;
    }

    /**
     * @return array<int,CustomField>
     */
    private function indexedFields(Model $record): array
    {
        return array_filter($this->fields->all($record), static fn (CustomField $field): bool => $field->isIndexed());
    }

    /**
     * What rows() needs of each field, read once: an attribute of a
     * definition costs more than the row it describes.
     *
     * @param  array<int,CustomField>  $fields
     * @return array<int,array{id:int,code:string,type:FieldType,column:string|null,multiple:bool,per_site:bool,translatable:bool}>
     */
    private function plan(array $fields): array
    {
        return array_map(static fn (CustomField $field): array => [
            'id' => $field->id,
            'code' => $field->code,
            'type' => $field->type,
            'column' => $field->type->indexColumn(),
            'multiple' => $field->type->isMultiple(),
            'per_site' => $field->per_site,
            'translatable' => $field->translatable,
        ], $fields);
    }

    /**
     * @param  array<int,array{id:int,code:string,type:FieldType,column:string|null,multiple:bool,per_site:bool,translatable:bool}>  $plan
     * @return array<int,array<string,mixed>>
     */
    private function rows(Model $record, array $plan): array
    {
        $stored = $this->fields->stored($record);
        $type = $record->getMorphClass();
        $id = $record->getKey();
        $rows = [];

        foreach ($plan as $field) {
            $value = $stored[$field['code']] ?? null;

            if ($value === null) {
                continue;
            }

            $bySite = $field['per_site'] ? (is_array($value) ? $value : []) : [0 => $value];

            foreach ($bySite as $site => $siteValue) {
                $byLocale = $field['translatable']
                    ? $this->byLocale(is_array($siteValue) ? $siteValue : [])
                    : ['' => $siteValue];

                foreach ($byLocale as $locale => $localeValue) {
                    foreach ($this->typed($field, $localeValue) as $typed) {
                        $rows[] = [
                            'entity_type' => $type,
                            'entity_id' => $id,
                            'field_id' => $field['id'],
                            'site_id' => $site,
                            'locale' => $locale,
                            ...$typed,
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Every language of the site list gets a row, the missing ones with the
     * default language's text: a filter in German finds the record whose
     * German text falls back to English, as the page shows it.
     *
     * @param  array<string,mixed>  $translations
     * @return array<string,mixed>
     */
    private function byLocale(array $translations): array
    {
        $values = [];

        // An empty text is skipped by typed(), as any empty value.
        foreach ([...array_keys($translations), ...$this->locales()] as $locale) {
            $values[$locale] = $this->fields->translation($translations, (string) $locale);
        }

        return $values;
    }

    /**
     * @param  array{type:FieldType,column:string|null,multiple:bool}  $field
     * @return array<int,array{value_num:float|int|null,value_str:string|null,value_date:string|null}>
     */
    private function typed(array $field, mixed $value): array
    {
        $type = $field['type'];
        $column = $field['column'];
        $rows = [];

        foreach ($field['multiple'] ? (array) $value : [$value] as $item) {
            $item = $field['multiple'] ? (is_scalar($item) ? $item : null) : $type->normalize($item);

            if ($item === null) {
                continue;
            }

            $row = ['value_num' => null, 'value_str' => null, 'value_date' => null];

            // A yes/no is bound as 1 or 0 by the connection.
            $row[$column] = $column === 'value_str' ? mb_substr((string) $item, 0, 191) : $item;

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int,string>
     */
    private function locales(): array
    {
        if ($this->locales === null) {
            $this->locales = Language::query()->pluck('code')->all();
        }

        return $this->locales;
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function insert(array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $this->table()->insert($chunk);
        }
    }

    private function table(): Builder
    {
        return $this->connection()->table(self::TABLE);
    }

    /**
     * The default connection: the index is joined with the modules' tables,
     * so it lives in the same database.
     */
    private function connection(): ConnectionInterface
    {
        return DB::connection();
    }
}
