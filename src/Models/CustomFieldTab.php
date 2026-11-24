<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Models;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\Support\ModuleList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\Translatable\Attributes\Translatable as TranslatableColumns;

/**
 * A tab of a module's form that custom fields can be put on.
 *
 * @property int $id
 * @property string $module
 * @property string $code
 * @property int $sort
 * @property int|null $site_id
 */
#[TranslatableColumns(['label'])]
class CustomFieldTab extends Model implements Administrable
{
    use InteractsWithAdmin, Translatable;

    /** The tab a field falls back to when its own is gone. */
    public const DEFAULT = 'params';

    protected $table = 'custom_field_tabs';

    protected $fillable = ['module', 'code', 'label', 'sort', 'site_id'];

    /** @var list<string> */
    protected $appends = ['module_label'];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'site_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static function (self $tab): void {
            app(CustomFields::class)->flush();
        });

        // The fields of a deleted tab move to the default one: none is lost.
        static::deleted(static function (self $tab): void {
            CustomField::query()->where('module', $tab->module)->where('tab', $tab->code)->update(['tab' => self::DEFAULT]);

            app(CustomFields::class)->flush();
        });
    }

    public function labelText(): string
    {
        // No label (null) or an empty one: the code.
        return $this->getTranslation('label', app()->getLocale()) ?: $this->code;
    }

    public static function getAdminLabel(): string
    {
        return __('atrium-custom-fields::custom-fields.tabs.module');
    }

    public static function getAdminCreateLabel(): string
    {
        return __('atrium-custom-fields::custom-fields.tabs.create');
    }

    /** Service records, not content: no custom fields, no link target. */
    public static function isContentModule(): bool
    {
        return false;
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'custom_field_tabs';
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
     * `?module=…` — the tabs of one module, which is how the "Tabs" button
     * of every module's list opens this one.
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

        return [
            SelectField::make('module')->label($t('fields.module'))->options(app(ModuleList::class)->options())->required()
                ->hint($t('hints.fixed')),
            TextField::make('label')->label($t('fields.label'))->required(),
            TextField::make('code')->label($t('fields.code'))->required()
                ->rules(['regex:/^[a-z][a-z0-9_]*$/', 'max:64'])->hint($t('tabs.hints.code')),
            NumberField::make('sort')->label(__('atrium::fields.standard.sort'))->default(500)->hint($t('tabs.hints.sort')),
            SelectField::make('site_id')->label($t('fields.site'))->options(Site::query()->orderBy('id')->pluck('name', 'id')->all())
                ->placeholder($t('all_sites'))->hint($t('tabs.hints.site')),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        $t = static fn (string $key): string => __('atrium-custom-fields::custom-fields.'.$key);

        return [
            TextColumn::make('label')->label($t('fields.label'))->sortable()->searchable(),
            TextColumn::make('code')->label($t('fields.code'))->sortable()->searchable(),
            TextColumn::make('module_label')->label($t('fields.module')),
            TextColumn::make('sort')->label(__('atrium::fields.standard.sort'))->sortable(),
        ];
    }

    protected function getModuleLabelAttribute(): string
    {
        return app(ModuleList::class)->label((string) $this->module);
    }

    /**
     * Checks that need the whole submission: the module and the code are
     * fixed once the tab exists, and a new code is free in the module.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function transformAdminData(array $data): array
    {
        $t = static fn (string $key): string => __('atrium-custom-fields::custom-fields.'.$key);
        $errors = [];

        // Fields keep the tab by its code: it stays.
        if ($this->exists) {
            foreach (['module', 'code'] as $key) {
                if (array_key_exists($key, $data) && $data[$key] !== $this->getAttribute($key)) {
                    $errors[$key] = $t('errors.fixed');
                }
            }
        }

        $module = $data['module'] ?? '';
        $code = $data['code'] ?? '';
        $model = app(ModuleList::class)->modelClass($module);

        if (! $this->exists && $code !== '' && $model !== null && in_array($code, $this->takenCodes($model, $module), true)) {
            $errors['code'] = $t('tabs.errors.code_taken');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /**
     * The tab names the module has already: its form's, the ones other
     * modules add, the default one and the ones added here, on every site.
     *
     * @param  class-string  $model
     * @return array<int,string>
     */
    private function takenCodes(string $model, string $module): array
    {
        $added = array_map(
            static fn (mixed $tab): string => is_array($tab) ? (string) ($tab['name'] ?? '') : $tab->getName(),
            Hook::apply('atrium.module.tabs', [], $model),
        );

        return [
            self::DEFAULT,
            ...array_keys(app(ModuleList::class)->tabsOf($model)),
            ...$added,
            ...static::query()->where('module', $module)->pluck('code')->all(),
        ];
    }
}
