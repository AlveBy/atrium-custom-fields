<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Support;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Tabs\Tab;
use Atrium\CustomFields\Models\CustomFieldTab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * The modules custom fields can be added to (the content modules: not logs,
 * form submissions or the fields screen itself), and how a module is named:
 * a definition keeps the model's morph class (its class name, or its alias
 * in the morph map), the same string Laravel keeps in polymorphic columns.
 */
final class ModuleList
{
    /** Names a field code may never take. */
    private const RESERVED = ['id', 'custom', 'created_at', 'updated_at', 'admin_url', 'public_url', 'fullPath'];

    public function __construct(private readonly ResourceRegistry $registry) {}

    /**
     * @return array<string,class-string<Model&Administrable>> morph class => model class
     */
    public function models(): array
    {
        $models = [];

        foreach ($this->registry->content() as $model) {
            $models[(new $model)->getMorphClass()] = $model;
        }

        return $models;
    }

    /**
     * @return array<string,string> morph class => label, for a select
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->models() as $morph => $model) {
            $options[$morph] = $model::getAdminLabel();
        }

        asort($options);

        return $options;
    }

    public function label(string $morph): string
    {
        $model = $this->modelClass($morph);

        return $model !== null ? $model::getAdminLabel() : $morph;
    }

    /**
     * The admin module's model of a morph class; null for anything else.
     *
     * @return class-string<Model&Administrable>|null
     */
    public function modelClass(string $morph): ?string
    {
        $class = Relation::getMorphedModel($morph) ?? $morph;

        return class_exists($class) && is_subclass_of($class, Model::class) && is_subclass_of($class, Administrable::class) ? $class : null;
    }

    /**
     * The morph class of a model given as an instance, a class, a morph
     * alias or an admin key ("products").
     */
    public function morphOf(Model|string $model): string
    {
        if ($model instanceof Model) {
            return $model->getMorphClass();
        }

        $class = $this->find($model);

        if ($class === null) {
            throw new InvalidArgumentException("[{$model}] is not a model or an admin module.");
        }

        return (new $class)->getMorphClass();
    }

    /**
     * The admin module's model given as a class, a morph alias or an admin
     * key ("products"); null for anything else. A key wins over a class.
     *
     * @return class-string<Model&Administrable>|null
     */
    public function find(string $name): ?string
    {
        return $this->modelClass($this->registry->model($name) ?? $name);
    }

    /**
     * Every tab any module's form has, and the ones added here, for the
     * definition's "Tab" select.
     *
     * @return array<string,string> name => label
     */
    public function tabOptions(): array
    {
        $tabs = [];

        foreach ($this->models() as $model) {
            foreach ($this->tabsOf($model) as $name => $label) {
                $tabs[$name] ??= $label === $name ? $name : "{$label} ({$name})";
            }
        }

        foreach (CustomFieldTab::query()->orderBy('sort')->orderBy('id')->get() as $tab) {
            $tabs[$tab->code] ??= "{$tab->labelText()} ({$tab->code})";
        }

        return $tabs === [] ? ['params' => 'params'] : $tabs;
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string,string> name => label, in the form's order
     */
    public function tabsOf(string $model): array
    {
        if (! method_exists($model, 'getAdminTabs') || (method_exists($model, 'usesTabs') && ! $model::usesTabs())) {
            return [];
        }

        $tabs = [];

        foreach ($model::getAdminTabs() as $tab) {
            $name = $tab instanceof Tab ? $tab->getName() : $tab['name'] ?? '';
            $label = $tab instanceof Tab ? $tab->getLabel() : $tab['label'] ?? $name;

            if ($name !== '') {
                $tabs[$name] = $label;
            }
        }

        return $tabs;
    }

    /**
     * Names a new field of the module may not take: the table's columns
     * and the fields of the module's own form.
     *
     * @param  class-string<Model&Administrable>  $model
     * @return array<int,string>
     */
    public function takenNames(string $model): array
    {
        $instance = new $model;
        $names = [...self::RESERVED, ...Schema::connection($instance->getConnectionName())->getColumnListing($instance->getTable())];

        foreach ($model::getAdministrationFields() as $field) {
            $names[] = $field->getName();
        }

        if (method_exists($model, 'getSeoAdminFields')) {
            foreach ($model::getSeoAdminFields() as $field) {
                $names[] = $field->getName();
            }
        }

        return $names;
    }
}
