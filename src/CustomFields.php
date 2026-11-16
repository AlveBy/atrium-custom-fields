<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields;

use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Jobs\ReindexField;
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Support\ModuleList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * The definitions of every module and the values of one record.
 *
 * A record keeps its values in one JSON column (`custom` by default):
 *
 *     {"color": "red", "sizes": ["s", "m"], "subtitle": {"en": "…", "de": "…"},
 *      "price": {"1": 10.5, "2": 12}}
 *
 * A translatable field is an object by language, a per-site field an object
 * by site id, both together an object by site of objects by language. A
 * field promoted to a real column keeps its value there instead.
 *
 *     app(CustomFields::class)->get($product, 'color');        // in the current site and language
 *     app(CustomFields::class)->set($product, 'color', 'red'); // then $product->save()
 *
 * Definitions are read once per process and forgotten when one changes.
 */
final class CustomFields
{
    /** @var array<string,array<int,CustomField>>|null morph class => fields */
    private ?array $definitions = null;

    private ?bool $installed = null;

    public function __construct(private readonly ModuleList $modules) {}

    public function column(): string
    {
        return (string) config('atrium-custom-fields.column', 'custom');
    }

    /*
    |--------------------------------------------------------------------------
    | Definitions
    |--------------------------------------------------------------------------
    */

    /**
     * Every field of the module, whatever site it is limited to.
     *
     * @return array<int,CustomField>
     */
    public function all(Model|string $model): array
    {
        if (! $this->isInstalled()) {
            return [];
        }

        $this->definitions ??= CustomField::query()->orderBy('sort')->orderBy('id')->get()->groupBy('module')
            ->map(static fn ($fields): array => $fields->all())->all();

        return $this->definitions[$this->modules->morphOf($model)] ?? [];
    }

    /**
     * The fields of the module on the current site.
     *
     * @return array<int,CustomField>
     */
    public function fields(Model|string $model): array
    {
        $site = $this->site();

        return array_values(array_filter($this->all($model), static fn (CustomField $field): bool => $field->site_id === null || $field->site_id === $site));
    }

    public function field(Model|string $model, string $code): ?CustomField
    {
        foreach ($this->fields($model) as $field) {
            if ($field->code === $code) {
                return $field;
            }
        }

        return null;
    }

    public function fieldOrFail(Model|string $model, string $code): CustomField
    {
        return $this->field($model, $code)
            ?? throw new InvalidArgumentException("No custom field [{$code}] in [".(is_string($model) ? $model : $model::class).'].');
    }

    /**
     * Whether any module has fields at all — the cheap check before work
     * done on every model event.
     */
    public function hasFieldsFor(Model $model): bool
    {
        return $this->all($model) !== [];
    }

    public function flush(): void
    {
        $this->definitions = null;
        $this->installed = null;
    }

    public function isInstalled(): bool
    {
        return $this->installed ??= Schema::hasTable('custom_fields');
    }

    /*
    |--------------------------------------------------------------------------
    | Values
    |--------------------------------------------------------------------------
    */

    /**
     * The value in the given (by default the current) site and language;
     * a missing translation falls back to the default language.
     */
    public function get(Model $record, string $code, ?string $locale = null, ?int $site = null): mixed
    {
        $field = $this->fieldOrFail($record, $code);

        return $this->value($record, $field, $locale, $site);
    }

    public function value(Model $record, CustomField $field, ?string $locale = null, ?int $site = null): mixed
    {
        if ($field->column !== null) {
            return $record->getAttribute($field->column);
        }

        $value = $this->stored($record)[$field->code] ?? null;

        if ($field->per_site) {
            $value = is_array($value) ? ($value[$site ?? $this->site()] ?? null) : null;
        }

        if ($field->translatable) {
            $value = is_array($value) ? $this->translation($value, $locale ?? $this->locale()) : null;
        }

        return $value;
    }

    /**
     * Write a value onto the record without saving it. A translatable field
     * takes the text of one language, or an array of several by code.
     */
    public function set(Model $record, string $code, mixed $value, ?string $locale = null, ?int $site = null): void
    {
        $this->write($record, $this->fieldOrFail($record, $code), $value, $locale, $site);
    }

    public function write(Model $record, CustomField $field, mixed $value, ?string $locale = null, ?int $site = null): void
    {
        $type = $field->type;

        if ($field->column !== null) {
            $record->setAttribute($field->column, $type->normalize($value));

            return;
        }

        $values = $this->stored($record);
        $current = $values[$field->code] ?? null;

        if ($field->translatable) {
            $translations = is_array($value) ? $value : [$locale ?? $this->locale() => $value];
            $previous = $field->per_site ? (is_array($current) ? ($current[$site ?? $this->site()] ?? []) : []) : $current;
            $value = array_filter(
                [...(is_array($previous) ? $previous : []), ...array_map($type->normalize(...), $translations)],
                static fn (mixed $text): bool => $text !== null,
            );
            $value = $value === [] ? null : $value;
        } else {
            $value = $type->normalize($value);
        }

        if ($field->per_site) {
            $sites = is_array($current) ? $current : [];
            $sites[$site ?? $this->site()] = $value;
            $value = array_filter($sites, static fn (mixed $item): bool => $item !== null);
            $value = $value === [] ? null : $value;
        }

        if ($value === null) {
            unset($values[$field->code]);
        } else {
            $values[$field->code] = $value;
        }

        $this->store($record, $values);
    }

    /**
     * Everything in the record's JSON column.
     *
     * @return array<string,mixed>
     */
    public function stored(Model $record): array
    {
        $raw = $record->getAttribute($this->column());

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param  array<string,mixed>  $values
     */
    private function store(Model $record, array $values): void
    {
        $column = $this->column();

        // A model that casts the column (HasCustomFields does) wants the
        // array; one that does not gets the JSON text it would read back.
        if ($record->hasCast($column)) {
            $record->setAttribute($column, $values === [] ? null : $values);
        } else {
            $record->setAttribute($column, $values === [] ? null : json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        }
    }

    /**
     * @param  array<string,mixed>  $translations
     */
    public function translation(array $translations, string $locale): mixed
    {
        $value = $translations[$locale] ?? null;

        if ($value === null || $value === '') {
            $value = $translations[$this->defaultLocale()] ?? null;
        }

        return $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Site and language
    |--------------------------------------------------------------------------
    */

    public function site(): int
    {
        // No site saved yet: the values belong to every site.
        return (int) app(Sites::class)->current()->id;
    }

    /**
     * The content language of the request: the app locale when it is one
     * of the site's languages, the default language otherwise.
     */
    public function locale(): string
    {
        $languages = app(ContentLanguages::class);
        $locale = app()->getLocale();

        return $languages->has($locale) ? $locale : $this->defaultLocale();
    }

    public function defaultLocale(): string
    {
        return app(ContentLanguages::class)->default() ?: config()->string('app.fallback_locale');
    }

    /*
    |--------------------------------------------------------------------------
    | Changes of the definitions
    |--------------------------------------------------------------------------
    */

    /**
     * The module's table gets its JSON column with the first field, and
     * the index follows a change of what is indexed.
     */
    public function fieldSaved(CustomField $field): void
    {
        $this->flush();

        $model = $this->modules->modelClass($field->module);

        if ($model !== null) {
            $this->ensureColumn($model);
        }

        $indexChanged = $field->wasRecentlyCreated
            ? $field->isIndexed()
            : $field->wasChanged(['filterable', 'sortable', 'per_site', 'translatable', 'column', 'options']);

        if ($indexChanged) {
            ReindexField::dispatch($field->id);
        }
    }

    public function fieldDeleted(CustomField $field): void
    {
        $this->flush();
        app(Indexer::class)->forgetField($field->id);
    }

    /**
     * Add the JSON column to the module's table if it is not there yet —
     * a migration of its own, run when the first field is created.
     *
     * @param  class-string<Model>  $model
     */
    private function ensureColumn(string $model): void
    {
        $instance = new $model;
        $schema = Schema::connection($instance->getConnectionName());
        $column = $this->column();

        if ($schema->hasColumn($instance->getTable(), $column)) {
            return;
        }

        $schema->table($instance->getTable(), static function (Blueprint $table) use ($column): void {
            $table->json($column)->nullable();
        });
    }
}
