<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Jobs;

use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Support\ModuleList;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Rebuild the index rows of one field after it became filterable, sortable,
 * per-site or translatable. Runs at once on the sync queue, in the
 * background on a real one: a field of a big catalogue can take a while.
 */
final class ReindexField implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $fieldId) {}

    public function handle(Indexer $indexer, ModuleList $modules): void
    {
        $field = CustomField::query()->find($this->fieldId);
        $model = $field !== null ? $modules->modelClass($field->module) : null;

        if ($field === null || $model === null) {
            return;
        }

        $indexer->reindex($model, $field);
    }
}
