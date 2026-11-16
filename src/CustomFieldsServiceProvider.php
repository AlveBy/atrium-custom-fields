<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields;

use Atrium\CustomFields\Admin\CustomFieldsExtension;
use Atrium\CustomFields\Console\PromoteCommand;
use Atrium\CustomFields\Console\ReindexCommand;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Query\CustomQuery;
use Atrium\CustomFields\Support\ModuleList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class CustomFieldsServiceProvider extends ServiceProvider
{
    /**
     * The "Fields" screen and the migrations come from extra.atrium in
     * composer.json, once `atrium:module:install alveby/atrium-custom-fields`
     * has run.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atrium-custom-fields.php', 'atrium-custom-fields');

        $this->app->singleton(ModuleList::class);
        $this->app->singleton(CustomFields::class);
        $this->app->singleton(CustomQuery::class);
        $this->app->singleton(Indexer::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'atrium-custom-fields');

        CustomFieldsExtension::register();

        $this->registerQueryMacros();
        $this->registerModelListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([ReindexCommand::class, PromoteCommand::class]);

            $this->publishes([
                __DIR__.'/../config/atrium-custom-fields.php' => config_path('atrium-custom-fields.php'),
            ], 'atrium-custom-fields-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/atrium-custom-fields'),
            ], 'atrium-custom-fields-lang');
        }
    }

    private function registerQueryMacros(): void
    {
        $query = fn (): CustomQuery => $this->app->make(CustomQuery::class);

        // whereCustom('color', 'red') or whereCustom('weight', '<=', 5)
        Builder::macro('whereCustom', function (string $code, mixed $operator = null, mixed $value = null) use ($query): Builder {
            /** @var Builder $this */
            [$operator, $value] = func_num_args() === 2 ? ['=', $operator] : [(string) $operator, $value];

            return $query()->where($this, $code, $operator, $value);
        });

        Builder::macro('orWhereCustom', function (string $code, mixed $operator = null, mixed $value = null) use ($query): Builder {
            /** @var Builder $this */
            [$operator, $value] = func_num_args() === 2 ? ['=', $operator] : [(string) $operator, $value];

            return $query()->where($this, $code, $operator, $value, 'or');
        });

        Builder::macro('whereCustomBetween', function (string $code, mixed $min, mixed $max) use ($query): Builder {
            /** @var Builder $this */
            return $query()->between($this, $code, $min, $max);
        });

        Builder::macro('whereCustomFilters', function (array $filters) use ($query): Builder {
            /** @var Builder $this */
            return $query()->filters($this, $filters);
        });

        Builder::macro('orderByCustom', function (string $code, string $direction = 'asc') use ($query): Builder {
            /** @var Builder $this */
            return $query()->orderBy($this, $code, $direction);
        });
    }

    /**
     * The index follows every record of a module with indexed fields,
     * however it was saved: the admin, the API, a seeder, a sync job.
     */
    private function registerModelListeners(): void
    {
        $sync = function (string $event, array $payload): void {
            $record = $payload[0] ?? null;

            if (! $record instanceof Model || ! $this->app->make(CustomFields::class)->hasFieldsFor($record)) {
                return;
            }

            $column = $this->app->make(CustomFields::class)->column();

            if (str_starts_with($event, 'eloquent.saved') && ! $record->wasRecentlyCreated && ! $record->wasChanged($column)) {
                return;
            }

            $this->app->make(Indexer::class)->sync($record);
        };

        Event::listen('eloquent.saved: *', $sync);
        Event::listen('eloquent.restored: *', $sync);

        Event::listen('eloquent.deleted: *', function (string $event, array $payload): void {
            $record = $payload[0] ?? null;

            if ($record instanceof Model && $this->app->make(CustomFields::class)->hasFieldsFor($record)) {
                $this->app->make(Indexer::class)->forget($record);
            }
        });
    }
}
