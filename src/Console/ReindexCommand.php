<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Console;

use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Support\ModuleList;
use Illuminate\Console\Command;

final class ReindexCommand extends Command
{
    protected $signature = 'atrium:custom-fields:reindex
                            {module? : A module (model class, morph alias or admin key); every module by default}
                            {--field= : Only this field (its code)}';

    protected $description = 'Rebuild the filter index of the custom fields';

    public function handle(CustomFields $fields, Indexer $indexer, ModuleList $modules): int
    {
        $fields->flush();
        $module = $this->argument('module');

        if (is_string($module)) {
            $model = $modules->find($module);

            if ($model === null) {
                $this->error("Unknown module [{$module}].");

                return self::FAILURE;
            }

            $models = [$model];
        } else {
            $models = array_filter(
                $modules->models(),
                static fn (string $model): bool => $fields->all($model) !== [],
            );
        }

        $code = $this->option('field');

        foreach ($models as $model) {
            $only = is_string($code)
                ? (array_values(array_filter($fields->all($model), static fn ($field): bool => $field->code === $code))[0] ?? null)
                : null;

            if (is_string($code) && $only === null) {
                $this->error("No custom field [{$code}] in [{$model}].");

                return self::FAILURE;
            }

            $count = $indexer->reindex($model, $only);
            $this->line("{$model}: {$count} records");
        }

        return self::SUCCESS;
    }
}
