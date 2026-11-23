<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Models;

use Atrium\Admin\Columns\BooleanColumn;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\FieldType;
use Atrium\CustomFields\Support\ModuleList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\Translatable\Attributes\Translatable as TranslatableColumns;

/**
 * One custom field of one module: what it holds, where it is shown and
 * whether it is filtered, sorted or searched by.
 *
 * @property int $id
 * @property string $module
 * @property string $code
 * @property FieldType $type
 * @property string|null $link_module
 * @property string $tab
 * @property int $sort
 * @property int|null $site_id
 * @property bool $required
 * @property bool $per_site
 * @property bool $translatable
 * @property bool $filterable
 * @property bool $sortable
 * @property bool $searchable
 * @property bool $show_in_list
 * @property string|null $column
 */
// The columns go in the attribute, not in $translatable: that name is the
// field's own "translatable" flag here.
#[TranslatableColumns(['label', 'hint', 'options'])]
class CustomField extends Model implements Administrable
{
    use InteractsWithAdmin, Translatable;

    protected $table = 'custom_fields';

    protected $fillable = [
        'module', 'code', 'type', 'label', 'hint', 'options', 'link_module', 'tab', 'sort', 'site_id',
        'required', 'per_site', 'translatable', 'filterable', 'sortable', 'searchable', 'show_in_list',
    ];

    /** @var list<string> */
    protected $appends = ['type_label', 'module_label'];

    /** @var array<string,array<string,string>> parsed options per locale */
    private array $parsedOptions = [];

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'sort' => 'integer',
            'site_id' => 'integer',
            'required' => 'boolean',
            'per_site' => 'boolean',
            'translatable' => 'boolean',
            'filterable' => 'boolean',
            'sortable' => 'boolean',
            'searchable' => 'boolean',
            'show_in_list' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Flags a type cannot have are dropped rather than refused: the form
        // and the API get a message from transformAdminData() first.
        static::saving(static function (self $field): void {
            $type = $field->type;
            $field->translatable = $field->translatable && $type->canBeTranslatable() && $field->column === null;
            $field->per_site = $field->per_site && $field->column === null;
            $field->searchable = $field->searchable && $type->canBeSearchable() && ! $field->per_site;
            $field->filterable = $field->filterable && $type->indexColumn() !== null;
            $field->sortable = $field->sortable && $type->canBeSortable();
            $field->link_module = $type === FieldType::Link ? $field->link_module : null;
            $field->tab = $field->tab ?: 'params';
        });

        static::saved(static function (self $field): void {
            app(CustomFields::class)->fieldSaved($field);
        });

        static::deleted(static function (self $field): void {
            app(CustomFields::class)->fieldDeleted($field);
        });
    }

    /**
     * Whether the values go to the typed index: the field is filtered or
     * sorted by, and is not a real column of the table yet.
     */
    public function isIndexed(): bool
    {
        return ($this->filterable || $this->sortable) && $this->type->indexColumn() !== null && $this->column === null;
    }

    public function labelText(?string $locale = null): string
    {
        // No label (null) or an empty one: the code.
        return $this->getTranslation('label', $locale ?? app()->getLocale()) ?: $this->code;
    }

    public function hintText(): ?string
    {
        return $this->getTranslation('hint', app()->getLocale()) ?: null;
    }

    /**
     * The options of a list: value => label in the given language. The
     * values come from the default language, each line "value = Label" (or
     * just "Label", which is its own value); another language only gives
     * labels, by value or by line number.
     *
     * @return array<string,string>
     */
    public function optionList(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        if (isset($this->parsedOptions[$locale])) {
            return $this->parsedOptions[$locale];
        }

        $base = self::parseOptions((string) $this->getTranslation('options', $this->getFallbackLocale(), false));
        // A language without labels falls back to the base ones: the same.
        $translated = self::parseOptions((string) $this->getTranslation('options', $locale));
        $byLine = array_values($translated);
        $options = [];
        $line = 0;

        foreach ($base as $value => $label) {
            $options[$value] = $translated[$value] ?? ($byLine[$line] ?? $label);
            $line++;
        }

        return $this->parsedOptions[$locale] = $options;
    }

    /**
     * @return array<int,string>
     */
    public function optionValues(): array
    {
        return array_map('strval', array_keys($this->optionList($this->getFallbackLocale())));
    }

    /**
     * @return array<string,string>
     */
    private static function parseOptions(string $text): array
    {
        $options = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$value, $label] = str_contains($line, '=')
                ? array_map('trim', explode('=', $line, 2))
                : [$line, $line];

            if ($value !== '') {
                $options[$value] = $label !== '' ? $label : $value;
            }
        }

        return $options;
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'options') {
            $this->parsedOptions = [];
        }

        return parent::setAttribute($key, $value);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    public static function getAdminLabel(): string
    {
        return __('atrium-custom-fields::custom-fields.module');
    }

    public static function getAdminCreateLabel(): string
    {
        return __('atrium-custom-fields::custom-fields.create');
    }

    /** Service records, not content: no custom fields, no link target. */
    public static function isContentModule(): bool
    {
        return false;
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'custom_fields';
    }

    public static function getDefaultSortColumn(): string
    {
        return 'sort';
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * `?module=…` — the fields of one module, which is how the "Fields"
     * button of every module's list opens this one.
     */
    public static function getAdministrationFilters(): array
    {
        return ['module'];
    }

    public function getAdminTitle(): string
    {
        return $this->labelText();
    }

    public static function getAdministrationFields(): array
    {
        $t = static fn (string $key): string => __('atrium-custom-fields::custom-fields.'.$key);
        $modules = app(ModuleList::class);

        return [
            SelectField::make('module')->label($t('fields.module'))->options($modules->options())->required()
                ->hint($t('hints.fixed')),
            TextField::make('label')->label($t('fields.label'))->required(),
            TextField::make('code')->label($t('fields.code'))->required()
                ->rules(['regex:/^[a-z][a-z0-9_]*$/', 'max:64'])->hint($t('hints.code')),
            SelectField::make('type')->label($t('fields.type'))->fromEnum(FieldType::class)->required()
                ->default(FieldType::String->value)->hint($t('hints.fixed')),
            TextareaField::make('options')->label($t('fields.options'))->rows(6)->hint($t('hints.options')),
            SelectField::make('link_module')->label($t('fields.link_module'))->options($modules->options())
                ->hint($t('hints.link_module')),
            TextField::make('hint')->label($t('fields.hint')),
            SelectField::make('tab')->label($t('fields.tab'))->options($modules->tabOptions())->default('params')
                ->hint($t('hints.tab')),
            NumberField::make('sort')->label(__('atrium::fields.standard.sort'))->default(500),
            CheckboxField::make('required')->label($t('fields.required')),
            CheckboxField::make('show_in_list')->label($t('fields.show_in_list')),
            CheckboxField::make('filterable')->label($t('fields.filterable'))->hint($t('hints.filterable')),
            CheckboxField::make('sortable')->label($t('fields.sortable'))->hint($t('hints.sortable')),
            CheckboxField::make('searchable')->label($t('fields.searchable'))->hint($t('hints.searchable')),
            CheckboxField::make('translatable')->label($t('fields.translatable'))->hint($t('hints.translatable')),
            CheckboxField::make('per_site')->label($t('fields.per_site'))->hint($t('hints.per_site')),
            SelectField::make('site_id')->label($t('fields.site'))->options(self::siteOptions())
                ->placeholder($t('all_sites'))->hint($t('hints.site')),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        $t = static fn (string $key): string => __('atrium-custom-fields::custom-fields.'.$key);

        return [
            TextColumn::make('label')->label($t('fields.label'))->sortable()->searchable(),
            TextColumn::make('code')->label($t('fields.code'))->sortable()->searchable(),
            TextColumn::make('type_label')->label($t('fields.type')),
            TextColumn::make('module_label')->label($t('fields.module')),
            BooleanColumn::make('filterable')->label($t('fields.filterable')),
            BooleanColumn::make('sortable')->label($t('fields.sortable')),
            TextColumn::make('sort')->label(__('atrium::fields.standard.sort'))->sortable(),
        ];
    }

    protected function getTypeLabelAttribute(): string
    {
        // The raw value: a field being created has no type yet.
        return FieldType::tryFrom((string) ($this->attributes['type'] ?? ''))?->label() ?? '';
    }

    protected function getModuleLabelAttribute(): string
    {
        return app(ModuleList::class)->label((string) $this->module);
    }

    /**
     * @return array<int|string,string>
     */
    private static function siteOptions(): array
    {
        return Site::query()->orderBy('id')->pluck('name', 'id')->all();
    }

    /**
     * Checks that need the whole submission: the code is free in the
     * module, the type allows the flags, a list has options.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function transformAdminData(array $data): array
    {
        $t = static fn (string $key, array $replace = []): string => __('atrium-custom-fields::custom-fields.errors.'.$key, $replace);
        $errors = [];

        // Module, code and type are fixed once the field exists: values in
        // the records are kept under the code, in the type's shape.
        if ($this->exists) {
            foreach (['module', 'code', 'type'] as $key) {
                $current = $key === 'type' ? $this->type->value : $this->getAttribute($key);

                if (array_key_exists($key, $data) && $data[$key] !== $current) {
                    $errors[$key] = $t('fixed');
                }
            }
        }

        // A new field: its module and code are the submitted ones.
        $module = $data['module'] ?? '';
        $code = $data['code'] ?? '';
        $type = FieldType::tryFrom((string) ($data['type'] ?? $this->attributes['type'] ?? ''));
        $modules = app(ModuleList::class);

        // The module is one of the select's options: the form's rules said so.
        if (! $this->exists && $code !== '' && ($model = $modules->modelClass($module)) !== null) {
            if (static::query()->where('module', $module)->where('code', $code)->exists()) {
                $errors['code'] = $t('code_taken');
            } elseif (in_array($code, $modules->takenNames($model), true)) {
                $errors['code'] = $t('code_column');
            }
        }

        if ($type !== null) {
            $flag = fn (string $key): bool => filter_var($data[$key] ?? $this->getAttribute($key), FILTER_VALIDATE_BOOLEAN);

            if ($flag('translatable') && ! $type->canBeTranslatable()) {
                $errors['translatable'] = $t('translatable');
            }

            // A column holds one value for every language and site.
            foreach (['translatable', 'per_site'] as $key) {
                if ($this->column !== null && $flag($key)) {
                    $errors[$key] = $t('promoted', ['column' => $this->column]);
                }
            }

            if ($flag('filterable') && $type->indexColumn() === null) {
                $errors['filterable'] = $t('filterable');
            }

            if ($flag('sortable') && ! $type->canBeSortable()) {
                $errors['sortable'] = $t('sortable');
            }

            if ($flag('searchable') && (! $type->canBeSearchable() || $flag('per_site'))) {
                $errors['searchable'] = $t('searchable');
            }

            if ($type->hasOptions() && self::emptyOptions($data['options'] ?? $this->getTranslations('options'))) {
                $errors['options'] = $t('options');
            }

            if ($type === FieldType::Link && $modules->modelClass((string) ($data['link_module'] ?? $this->link_module)) === null) {
                $errors['link_module'] = $t('link_module');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private static function emptyOptions(mixed $options): bool
    {
        $text = implode("\n", array_filter((array) $options, 'is_scalar'));

        return self::parseOptions($text) === [];
    }
}
