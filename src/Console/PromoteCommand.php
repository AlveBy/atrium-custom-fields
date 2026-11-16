<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Console;

use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\FieldType;
use Atrium\CustomFields\Support\ModuleList;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move a hot field (price, stock) out of the JSON into a real indexed column
 * of the module's table. whereCustom(), orderByCustom(), the facets, the
 * admin and the API keep working; the column is simply faster and can be
 * used as a plain column too.
 */
final class PromoteCommand extends Command
{
    protected $signature = 'atrium:custom-fields:promote
                            {module : The module (model class, morph alias or admin key)}
                            {code : The field}
                            {--column= : The column name; the code by default}';

    protected $description = 'Move a custom field into a real indexed column';

    private const CHUNK = 500;

    public function handle(CustomFields $fields, ModuleList $modules): int
    {
        $model = $modules->find((string) $this->argument('module'));

        if ($model === null) {
            $this->error('Unknown module ['.$this->argument('module').'].');

            return self::FAILURE;
        }

        $code = $this->argument('code');
        $field = array_values(array_filter($fields->all($model), static fn ($field): bool => $field->code === $code))[0] ?? null;

        if ($field === null) {
            $this->error("No custom field [{$code}] in [{$model}].");

            return self::FAILURE;
        }

        if ($field->column !== null) {
            $this->info("[{$code}] is in the column [{$field->column}] already.");

            return self::SUCCESS;
        }

        if ($field->translatable || $field->per_site || $field->type->isMultiple() || $field->type->indexColumn() === null) {
            $this->error('Only a single value of one language and every site fits a column: not a translatable, per-site, multiple, text or file field.');

            return self::FAILURE;
        }

        /** @var Model $instance */
        $instance = new $model;
        $table = $instance->getTable();
        $column = $this->option('column') ?: $code;
        $schema = Schema::connection($instance->getConnectionName());

        if ($schema->hasColumn($table, $column)) {
            $this->error("The table [{$table}] has a column [{$column}] already.");

            return self::FAILURE;
        }

        $schema->table($table, static function (Blueprint $blueprint) use ($field, $column): void {
            match ($field->type) {
                FieldType::Number, FieldType::Link => $blueprint->bigInteger($column)->nullable()->index(),
                FieldType::Decimal => $blueprint->decimal($column, 20, 6)->nullable()->index(),
                FieldType::Boolean => $blueprint->boolean($column)->nullable()->index(),
                FieldType::Date => $blueprint->date($column)->nullable()->index(),
                default => $blueprint->string($column, 191)->nullable()->index(),
            };
        });

        $json = $fields->column();
        $moved = 0;

        // Bare rows, no events: the values move, nothing else changes.
        $instance->newQueryWithoutScopes()
            ->select([$instance->getKeyName(), $json])
            ->whereNotNull($json)
            ->chunkById(self::CHUNK, function ($records) use ($instance, $fields, $field, $code, $column, $json, &$moved): void {
                foreach ($records as $record) {
                    $values = $fields->stored($record);

                    if (! array_key_exists($code, $values)) {
                        continue;
                    }

                    $value = $field->type->normalize($values[$code]);
                    unset($values[$code]);

                    $instance->newQueryWithoutScopes()->whereKey($record->getKey())->update([
                        // A yes/no is bound as 1 or 0 by the connection.
                        $column => $value,
                        $json => $values === [] ? null : json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
                    ]);
                    $moved++;
                }
            });

        // Saving the definition drops its index rows: the column is the index now.
        $field->column = $column;
        $field->save();

        $this->info("[{$code}] is in [{$table}.{$column}] now: {$moved} values moved.");

        return self::SUCCESS;
    }
}
