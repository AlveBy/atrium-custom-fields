<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Brands\Models\Brand;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\CustomFacets;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A hot field moved into a real column keeps the same API.
 */
final class PromoteTest extends TestCase
{
    /**
     * The command adds a column: DDL ends a transaction on MySQL, so every
     * test here migrates afresh instead.
     *
     * @var array<int,string>
     */
    protected $connectionsToTransact = [];

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::setUp();
    }

    public function test_a_promoted_field_lives_in_its_column_and_queries_keep_working(): void
    {
        $this->field('weight', 'decimal', ['filterable' => true, 'sortable' => true]);
        $this->field('color', 'string', ['filterable' => true]);
        $this->product('Apple', ['weight' => 1.5, 'color' => 'red']);
        $this->product('Plum', ['weight' => 0.5, 'color' => 'blue']);
        $this->product('Stone', ['color' => 'grey']);

        $this->artisan('atrium:custom-fields:promote', ['module' => Product::class, 'code' => 'weight'])
            ->expectsOutputToContain('2 values moved')
            ->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('catalog_products', 'weight'));
        $weight = $this->fields()->fieldOrFail(Product::class, 'weight');
        $this->assertSame('weight', $weight->column);
        $this->assertSame(0, DB::table(Indexer::TABLE)->where('field_id', $weight->id)->count());

        $apple = Product::query()->where('name', 'Apple')->firstOrFail();
        $this->assertSame(['color' => 'red'], $this->fields()->stored($apple));
        $this->assertEquals(1.5, $this->fields()->get($apple, 'weight'));

        $this->assertSame(['Apple'], Product::query()->whereCustom('weight', '>', 1)->pluck('name')->all());
        $this->assertSame(['Plum', 'Apple'], Product::query()->whereCustomBetween('weight', 0, 2)->orderByCustom('weight')->pluck('name')->all());
        $this->assertSame(['Apple', 'Plum', 'Stone'], Product::query()->orderByCustom('weight', 'desc')->pluck('name')->all());
        $this->assertSame(['weight' => ['min' => 0.5, 'max' => 1.5]], CustomFacets::for(Product::class)->ranges(['weight']));
        $this->assertEquals([0.5 => 1, 1.5 => 1], CustomFacets::for(Product::class)->counts(['weight'])['weight']);

        // New values go to the column.
        $this->fields()->set($apple, 'weight', 2);
        $apple->save();
        $this->assertEquals(2, DB::table('catalog_products')->where('id', $apple->id)->value('weight'));

        $this->artisan('atrium:custom-fields:promote', ['module' => Product::class, 'code' => 'weight'])
            ->expectsOutputToContain('already')
            ->assertSuccessful();
    }

    /**
     * @return array<string,array{string,array<string,mixed>}>
     */
    public static function unfit(): array
    {
        return [
            'translatable' => ['string', ['translatable' => true]],
            'per site' => ['string', ['per_site' => true]],
            'multiple' => ['multiselect', ['options' => ['en' => "s\nm"]]],
            'a long text' => ['text', []],
            'a file' => ['file', []],
        ];
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    #[DataProvider('unfit')]
    public function test_a_field_of_several_values_stays_in_the_json(string $type, array $attributes): void
    {
        $this->field('spec', $type, $attributes);

        $this->artisan('atrium:custom-fields:promote', ['module' => Product::class, 'code' => 'spec'])
            ->expectsOutputToContain('Only a single value of one language and every site fits a column')
            ->assertFailed();
        $this->assertFalse(Schema::hasColumn('catalog_products', 'spec'));
    }

    /**
     * The value, as stored in the column, and the column: its type and the
     * size it is given on MySQL and PostgreSQL.
     *
     * @return array<string,array{string,array<string,mixed>,mixed,mixed,array<string,mixed>}>
     */
    public static function types(): array
    {
        return [
            'a number' => ['number', [], 7, 7, ['type' => 'bigInteger']],
            'a link' => ['link', ['link_module' => Brand::class], 3, 3, ['type' => 'bigInteger']],
            'a decimal' => ['decimal', [], '1,5', 1.5, ['type' => 'decimal', 'total' => 20, 'places' => 6]],
            'a yes/no' => ['boolean', [], true, 1, ['type' => 'boolean']],
            'a date' => ['date', [], '2026-11-17', '2026-11-17', ['type' => 'date']],
            'a list' => ['select', ['options' => ['en' => "red\nblue"]], 'red', 'red', ['type' => 'string', 'length' => 191]],
        ];
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    #[DataProvider('types')]
    public function test_every_single_value_type_gets_its_column(string $type, array $attributes, mixed $value, mixed $stored, array $definition): void
    {
        // Another field first: the one asked for is found among several.
        $this->field('note', 'string');
        $this->field('spec', $type, ['filterable' => true, ...$attributes]);
        // A record with other values only comes first: it is skipped, not the end.
        $this->product('Plum', ['note' => 'Ничего, 2.0']);
        $this->product('Apple', ['spec' => $value, 'note' => 'Красный']);
        $columns = $this->columnsAdded();

        $this->artisan('atrium:custom-fields:promote', ['module' => 'catalog_products', 'code' => 'spec', '--column' => 'spec_value'])
            ->expectsOutputToContain('[spec] is in [catalog_products.spec_value] now: 1 values moved.')
            ->assertSuccessful();

        $this->assertEquals($stored, DB::table('catalog_products')->where('name', 'Apple')->value('spec_value'));
        $this->assertNull(DB::table('catalog_products')->where('name', 'Plum')->value('spec_value'));
        $this->assertSame(['Apple'], Product::query()->whereCustom('spec', $value)->pluck('name')->all());
        $this->assertEquals([['name' => 'spec_value', ...$definition, 'nullable' => true]], $columns());
        // What stays in the JSON is written as the record itself writes it.
        $this->assertSame('{"note":"Красный"}', $this->storedJson('Apple'));
    }

    public function test_a_whole_decimal_keeps_its_point_in_the_json(): void
    {
        $this->field('weight', 'decimal');
        $this->field('spec', 'number', ['filterable' => true]);
        $this->product('Apple', ['weight' => 2.0, 'spec' => 7]);

        $this->artisan('atrium:custom-fields:promote', ['module' => Product::class, 'code' => 'spec'])->assertSuccessful();

        $this->assertSame('{"weight":2.0}', $this->storedJson('Apple'));
    }

    public function test_an_unknown_module_or_field_is_refused(): void
    {
        $this->field('weight', 'decimal', ['filterable' => true]);

        $this->artisan('atrium:custom-fields:promote', ['module' => 'nope', 'code' => 'weight'])
            ->expectsOutputToContain('Unknown module [nope].')
            ->assertFailed();
        $this->artisan('atrium:custom-fields:promote', ['module' => Product::class, 'code' => 'height'])
            ->expectsOutputToContain('No custom field [height] in ['.Product::class.'].')
            ->assertFailed();
        $this->assertFalse(Schema::hasColumn('catalog_products', 'weight'));
        $this->assertFalse(Schema::hasColumn('catalog_products', 'height'));
    }

    public function test_a_column_of_the_table_is_never_overwritten(): void
    {
        $this->field('weight', 'decimal', ['filterable' => true]);
        $apple = $this->product('Apple', ['weight' => 1.5]);

        $this->artisan('atrium:custom-fields:promote', ['module' => Product::class, 'code' => 'weight', '--column' => 'name'])
            ->expectsOutputToContain('The table [catalog_products] has a column [name] already.')
            ->assertFailed();

        $this->assertSame('Apple', $apple->fresh()?->name);
        $this->assertNull($this->fields()->fieldOrFail(Product::class, 'weight')->column);
    }

    /**
     * The columns the next change of the products' table adds, as the
     * blueprint has them: type and size, whatever the database makes of them.
     *
     * @return callable(): list<array<string,mixed>>
     */
    private function columnsAdded(): callable
    {
        $blueprints = [];
        $this->app->bind(Blueprint::class, static function ($app, array $parameters) use (&$blueprints): Blueprint {
            return $blueprints[] = new Blueprint($parameters['connection'], $parameters['table'], $parameters['callback'] ?? null);
        });

        return static function () use (&$blueprints): array {
            $columns = [];

            foreach ($blueprints as $blueprint) {
                if ($blueprint->getTable() === 'catalog_products') {
                    foreach ($blueprint->getAddedColumns() as $column) {
                        $columns[] = array_intersect_key($column->getAttributes(), array_flip(['name', 'type', 'total', 'places', 'length', 'nullable']));
                    }
                }
            }

            return $columns;
        };
    }

    /**
     * The JSON as the database keeps it; MySQL writes a space after a colon.
     */
    private function storedJson(string $name): string
    {
        return str_replace('": ', '":', (string) DB::table('catalog_products')->where('name', $name)->value('custom'));
    }
}
