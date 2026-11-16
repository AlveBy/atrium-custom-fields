<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Brands\Models\Brand;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\CustomFieldsServiceProvider;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Query\CustomQuery;
use Atrium\CustomFields\Support\ModuleList;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the package wires into the app: its services, its config, the query
 * macros and the listeners that keep the index in step.
 */
final class ProviderTest extends TestCase
{
    /**
     * @return array<string,array{class-string}>
     */
    public static function services(): array
    {
        return [
            'module list' => [ModuleList::class],
            'values' => [CustomFields::class],
            'queries' => [CustomQuery::class],
            'index' => [Indexer::class],
        ];
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('services')]
    public function test_each_service_is_one_for_the_app(string $class): void
    {
        $this->assertSame($this->app->make($class), $this->app->make($class));
    }

    public function test_the_config_has_its_defaults_and_can_be_published(): void
    {
        $this->assertSame('custom', config('atrium-custom-fields.column'));

        $root = dirname(__DIR__, 2);
        $this->assertSame(
            [realpath($root.'/config/atrium-custom-fields.php') => config_path('atrium-custom-fields.php')],
            $this->realKeys(ServiceProvider::pathsToPublish(CustomFieldsServiceProvider::class, 'atrium-custom-fields-config')),
        );
        $this->assertSame(
            [realpath($root.'/lang') => $this->app->langPath('vendor/atrium-custom-fields')],
            $this->realKeys(ServiceProvider::pathsToPublish(CustomFieldsServiceProvider::class, 'atrium-custom-fields-lang')),
        );
    }

    public function test_an_operator_must_be_one_of_the_list(): void
    {
        $this->field('weight', 'decimal', ['filterable' => true]);

        // A number is no operator, not even as text.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator [5] for custom field [weight].');

        Product::query()->whereCustom('weight', 5, 1);
    }

    public function test_or_where_custom_takes_an_operator_too(): void
    {
        $this->field('weight', 'decimal', ['filterable' => true]);
        $this->product('Apple', ['weight' => 1.5]);
        $this->product('Plum', ['weight' => 0.5]);
        $this->product('Melon', ['weight' => 3]);

        $names = Product::query()->whereCustom('weight', '>', 2)->orWhereCustom('weight', '<', 1)->orderBy('name')->pluck('name')->all();

        $this->assertSame(['Melon', 'Plum'], $names);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator [5] for custom field [weight].');

        Product::query()->orWhereCustom('weight', 5, 1);
    }

    public function test_a_record_without_fields_or_no_record_at_all_is_left_alone(): void
    {
        $this->field('weight', 'decimal', ['filterable' => true]);
        $index = [];
        DB::listen(static function ($query) use (&$index): void {
            if (str_contains($query->sql, Indexer::TABLE)) {
                $index[] = $query->sql;
            }
        });

        Event::dispatch('eloquent.saved: '.Product::class, ['not a record']);
        Event::dispatch('eloquent.deleted: '.Product::class, ['not a record']);
        Brand::query()->create(['name' => 'Acme', 'code' => 'acme'])->delete();

        $this->assertSame([], $index);
    }

    public function test_a_restored_record_is_indexed_again(): void
    {
        $this->field('weight', 'decimal', ['filterable' => true]);
        $apple = $this->product('Apple', ['weight' => 1.5]);

        // Changed behind the index's back, as a restore from the trash does.
        DB::table('catalog_products')->where('id', $apple->id)->update(['custom' => json_encode(['weight' => 4.0])]);
        Event::dispatch('eloquent.restored: '.Product::class, [Product::query()->findOrFail($apple->id)]);

        $this->assertSame(['Apple'], Product::query()->whereCustom('weight', '>', 3)->pluck('name')->all());
    }

    /**
     * @param  array<string,string>  $paths
     * @return array<string,string>
     */
    private function realKeys(array $paths): array
    {
        $real = [];

        foreach ($paths as $from => $to) {
            $real[(string) realpath($from)] = $to;
        }

        return $real;
    }
}
