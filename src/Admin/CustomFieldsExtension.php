<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Admin;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Columns\BooleanColumn;
use Atrium\Admin\Columns\Column;
use Atrium\Admin\Columns\DateColumn;
use Atrium\Admin\Columns\ImageColumn;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\FileField;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tabs\Tab;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\FieldType;
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Models\CustomFieldTab;
use Atrium\CustomFields\Query\CustomQuery;
use Atrium\CustomFields\Support\ModuleList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Custom fields in the admin: on the module's form (the chosen tab), in its
 * list, in the API and in the MCP tools, through the core's filter hooks.
 */
final class CustomFieldsExtension
{
    public function __construct(
        private readonly CustomFields $fields,
        private readonly CustomQuery $query,
        private readonly ModuleList $modules,
    ) {}

    public static function register(): void
    {
        $self = static fn (): self => app(self::class);

        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => [...$fields, ...$self()->fields($model)]);
        Hook::filter('atrium.module.tabs', static fn (array $tabs, string $model): array => $self()->tabs($tabs, $model));
        Hook::filter('atrium.module.columns', static fn (array $columns, string $model): array => [...$columns, ...$self()->columns($model)]);
        Hook::filter('atrium.module.translatable', static fn (array $names, string $model): array => [...$names, ...$self()->translatable($model)]);
        Hook::filter('atrium.module.links', static fn (array $links, string $model): array => [...$links, ...$self()->links($model)]);
        Hook::filter('atrium.record.data', static fn (array $data, Model $record): array => $self()->fill($record, $data));
        Hook::filter('atrium.record.present', static fn (array $data, ?Model $record, string $context): array => $record === null ? $data : $self()->present($record, $data, $context));
        Hook::filter('atrium.query.filter', static fn (bool $done, Builder $query, string $name, mixed $value): bool => $done || $self()->filter($query, $name, $value));
        Hook::filter('atrium.query.sort', static fn (bool $done, Builder $query, string $name, string $direction): bool => $done || $self()->sort($query, $name, $direction));
        Hook::filter('atrium.query.search', static fn (bool $done, Builder $query, string $name, string $search): bool => $done || $self()->search($query, $name, $search));
    }

    public function fields(string $model): array
    {
        if ($this->isOwn($model)) {
            return [];
        }

        $definitions = $this->fields->fields($model);
        $own = array_keys($this->modules->tabsOf($model));
        $tabs = [...$own, ...array_map(static fn (CustomFieldTab $tab): string => $tab->code, $this->fields->tabs($model))];

        return array_map(function (CustomField $definition) use ($own, $tabs): Field {
            // A tab the form does not have (a deleted one, one of another site)
            // would hide the field: the module's first tab instead.
            $tab = $tabs === [] || in_array($definition->tab, $tabs, true) ? $definition->tab : ($own[0] ?? CustomFieldTab::DEFAULT);

            $field = $this->formField($definition)
                ->label($definition->labelText())
                ->required($definition->required)
                ->tab($tab)
                ->tabOrder(max(1, $definition->sort));

            if ($hint = $definition->hintText()) {
                $field->hint($hint);
            }

            return $field;
        }, $definitions);
    }

    /**
     * The module's form tabs with the added ones, each in its place: by its
     * sort number against the numbers of the form's tabs. A form whose tabs
     * have no numbers of their own gets 0, 10, 20 … in their order.
     *
     * @param  array<int,Tab|array<string,mixed>>  $tabs
     * @return array<int,Tab|array<string,mixed>>
     */
    public function tabs(array $tabs, string $model): array
    {
        $added = $this->isOwn($model) ? [] : $this->fields->tabs($model);

        if ($added === []) {
            return $tabs;
        }

        $orders = array_map(static fn (Tab|array $tab): int => (int) ($tab instanceof Tab ? $tab->getOrder() : ($tab['order'] ?? 0)), $tabs);

        if (count(array_unique($orders)) < count($orders)) {
            $tabs = array_map(static function (Tab|array $tab, int $position): Tab|array {
                if ($tab instanceof Tab) {
                    return $tab->order($position * 10);
                }

                $tab['order'] = $position * 10;

                return $tab;
            }, $tabs, array_keys($tabs));
        }

        foreach ($added as $tab) {
            $tabs[] = Tab::make($tab->code)->label($tab->labelText())->order($tab->sort);
        }

        return $tabs;
    }

    public function columns(string $model): array
    {
        if ($this->isOwn($model)) {
            return [];
        }

        $columns = [];

        foreach ($this->fields->fields($model) as $definition) {
            if (! $definition->show_in_list) {
                continue;
            }

            $columns[] = $this->column($definition)
                ->label($definition->labelText())
                ->sortable($this->sortable($definition))
                ->searchable($definition->searchable);
        }

        return $columns;
    }

    public function translatable(string $model): array
    {
        if ($this->isOwn($model)) {
            return [];
        }

        return array_values(array_map(
            static fn (CustomField $field): string => $field->code,
            array_filter($this->fields->fields($model), static fn (CustomField $field): bool => $field->translatable),
        ));
    }

    /**
     * Take the custom values out of the saved data into the record's column.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function fill(Model $item, array $data): array
    {
        if ($this->isOwn($item::class)) {
            return $data;
        }

        foreach ($this->fields->fields($item) as $field) {
            if (! array_key_exists($field->code, $data)) {
                continue;
            }

            $value = $data[$field->code];
            unset($data[$field->code]);

            // A list keeps only the values it offers.
            if ($field->type === FieldType::MultiSelect && is_array($value)) {
                $value = array_intersect($value, $field->optionValues());
            }

            $this->fields->write($item, $field, $value);
        }

        unset($data[$this->fields->column()]);

        return $data;
    }

    public function present(Model $item, array $data, string $context = 'form'): array
    {
        if ($this->isOwn($item::class) || ! $this->fields->hasFieldsFor($item)) {
            return $data;
        }

        unset($data[$this->fields->column()]);

        foreach ($this->fields->fields($item) as $field) {
            $data[$field->code] = $context === 'list'
                ? $this->listValue($item, $field)
                : $this->formValue($item, $field);
        }

        return $data;
    }

    private function filter(Builder $query, string $name, mixed $value): bool
    {
        $field = $this->find($query, $name);

        if ($field === null || ($field->column === null && ! $field->isIndexed())) {
            return false;
        }

        if (is_array($value) && (array_key_exists('min', $value) || array_key_exists('max', $value))) {
            $this->query->between($query, $name, $value['min'] ?? null, $value['max'] ?? null);
        } else {
            $this->query->where($query, $name, '=', is_string($value) && str_contains($value, ',') ? explode(',', $value) : $value);
        }

        return true;
    }

    private function sort(Builder $query, string $name, string $direction): bool
    {
        $field = $this->find($query, $name);

        if ($field === null || ! $this->sortable($field)) {
            return false;
        }

        $this->query->orderBy($query, $name, $direction);

        return true;
    }

    private function search(Builder $query, string $name, string $search): bool
    {
        $field = $this->find($query, $name);

        if ($field === null || ! $field->searchable) {
            return false;
        }

        $this->query->search($query, $field, $search);

        return true;
    }

    public function links(string $model): array
    {
        if ($this->isOwn($model) || $this->modules->modelClass((new $model)->getMorphClass()) === null) {
            return [];
        }

        $user = auth()->guard(config('atrium.auth.guard', 'admin'))->user();

        if (! $user instanceof AdminUser) {
            return [];
        }

        $morph = (new $model)->getMorphClass();
        $links = [];

        if (app(Permissions::class)->allowsRoute($user, 'admin.custom_fields.index')) {
            $links[] = [
                'name' => 'custom-fields',
                'label' => __('atrium-custom-fields::custom-fields.button', ['count' => count($this->fields->all($model))]),
                'href' => route('admin.custom_fields.index', ['module' => $morph]),
            ];
        }

        if (app(Permissions::class)->allowsRoute($user, 'admin.custom_field_tabs.index')) {
            $links[] = [
                'name' => 'custom-field-tabs',
                'label' => __('atrium-custom-fields::custom-fields.tabs.button', ['count' => count($this->fields->allTabs($model))]),
                'href' => route('admin.custom_field_tabs.index', ['module' => $morph]),
            ];
        }

        return $links;
    }

    /**
     * The definitions' own screens are not modules fields can be added to.
     */
    private function isOwn(string $model): bool
    {
        return $model === CustomField::class || $model === CustomFieldTab::class;
    }

    private function find(Builder $query, string $name): ?CustomField
    {
        $model = $query->getModel();

        return $this->isOwn($model::class) ? null : $this->fields->field($model, $name);
    }

    private function sortable(CustomField $field): bool
    {
        return $field->type->canBeSortable() && ($field->column !== null || ($field->sortable && $field->isIndexed()));
    }

    private function formField(CustomField $definition): Field
    {
        $code = $definition->code;
        $options = $definition->optionList();

        return match ($definition->type) {
            FieldType::String => TextField::make($code)->maxLength(255),
            FieldType::Text => TextareaField::make($code),
            FieldType::Number => NumberField::make($code),
            FieldType::Decimal => NumberField::make($code)->decimals(2)->step(0.01),
            FieldType::Boolean => CheckboxField::make($code),
            FieldType::Date => DateField::make($code),
            FieldType::Select => SelectField::make($code)->options($options),
            FieldType::MultiSelect => MultiSelectField::make($code)->options($options),
            FieldType::Link => BelongsToField::make($code)->model((string) $this->modules->modelClass((string) $definition->link_module)),
            FieldType::File => FileField::make($code),
            FieldType::Image => ImageField::make($code),
        };
    }

    private function column(CustomField $definition): Column
    {
        return match ($definition->type) {
            FieldType::Boolean => BooleanColumn::make($definition->code),
            FieldType::Date => DateColumn::make($definition->code),
            FieldType::Image => ImageColumn::make($definition->code),
            default => TextColumn::make($definition->code),
        };
    }

    /**
     * What the form edits: the current site's value, a translatable one as
     * the object of every language.
     */
    private function formValue(Model $item, CustomField $field): mixed
    {
        if ($field->column !== null) {
            return $item->getAttribute($field->column);
        }

        $value = $this->fields->stored($item)[$field->code] ?? null;

        if ($field->per_site) {
            $value = is_array($value) ? ($value[$this->fields->site()] ?? null) : null;
        }

        if ($field->translatable) {
            return is_array($value) ? $value : null;
        }

        return $value;
    }

    /**
     * What a list cell shows: the text in the default language, an option's
     * label rather than its value.
     */
    private function listValue(Model $item, CustomField $field): mixed
    {
        $value = $this->fields->value($item, $field, $this->fields->defaultLocale());

        if ($field->type->hasOptions()) {
            $options = $field->optionList();

            return implode(', ', array_map(
                static fn (mixed $option): string => $options[(string) $option] ?? (string) $option,
                (array) $value,
            ));
        }

        return $value;
    }
}
