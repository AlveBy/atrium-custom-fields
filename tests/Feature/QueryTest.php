<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Brands\Models\Brand;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\CustomFacets;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Query\CustomQuery;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Filters, sorting and facet counts over the typed index.
 */
final class QueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->field('color', 'select', ['options' => ['en' => "red = Red\nblue = Blue\ngreen = Green"], 'filterable' => true]);
        $this->field('sizes', 'multiselect', ['options' => ['en' => "s\nm\nl"], 'filterable' => true]);
        $this->field('weight', 'decimal', ['filterable' => true, 'sortable' => true]);
        $this->field('rating', 'number', ['sortable' => true]);
        $this->field('released', 'date', ['filterable' => true]);
        $this->field('organic', 'boolean', ['filterable' => true]);
        $this->field('note', 'string');

        $this->product('Apple', ['color' => 'red', 'sizes' => ['s', 'm'], 'weight' => 1.5, 'rating' => 4, 'released' => '2026-01-10', 'organic' => true]);
        $this->product('Plum', ['color' => 'blue', 'sizes' => ['m'], 'weight' => 0.5, 'rating' => 5, 'released' => '2026-03-01', 'organic' => false]);
        $this->product('Cherry', ['color' => 'red', 'sizes' => ['l'], 'weight' => 3, 'released' => '2025-12-31']);
        $this->product('Stone', []);
    }

    public function test_where_custom_filters_by_a_value_a_list_and_an_operator(): void
    {
        $this->assertNames(['Apple', 'Cherry'], Product::query()->whereCustom('color', 'red'));
        $this->assertNames(['Apple', 'Cherry', 'Plum'], Product::query()->whereCustom('color', ['red', 'blue']));
        $this->assertNames(['Plum', 'Stone'], Product::query()->whereCustom('color', '!=', 'red'));
        $this->assertNames(['Apple', 'Plum'], Product::query()->whereCustom('weight', '<', 2));
        $this->assertNames(['Apple', 'Cherry'], Product::query()->whereCustom('sizes', ['s', 'l']));
        $this->assertNames(['Apple'], Product::query()->whereCustom('organic', true));
        $this->assertNames(['Apple', 'Plum'], Product::query()->whereCustom('released', '>=', '2026-01-01'));
        $this->assertNames(['Apple', 'Plum'], Product::query()->whereCustom('color', 'blue')->orWhereCustom('sizes', 's'));
    }

    public function test_between_and_a_filter_panel_array(): void
    {
        $this->assertNames(['Apple', 'Cherry'], Product::query()->whereCustomBetween('weight', 1, 5));
        $this->assertNames(['Plum'], Product::query()->whereCustomBetween('weight', null, 1));

        $this->assertNames(['Apple'], Product::query()->whereCustomFilters([
            'color' => ['red'],
            'sizes' => ['m', 's'],
            'weight' => ['min' => '1', 'max' => ''],
            'released' => null,
        ]));
    }

    public function test_it_combines_with_ordinary_where_clauses(): void
    {
        $this->assertNames(['Cherry'], Product::query()->where('name', 'like', 'Ch%')->whereCustom('color', 'red'));
    }

    public function test_order_by_custom_puts_records_without_a_value_last(): void
    {
        $this->assertSame(['Plum', 'Apple', 'Cherry', 'Stone'], Product::query()->orderByCustom('weight')->pluck('name')->all());
        $this->assertSame(['Cherry', 'Apple', 'Plum', 'Stone'], Product::query()->orderByCustom('weight', 'desc')->pluck('name')->all());
        $this->assertSame(['Plum', 'Apple'], Product::query()->orderByCustom('rating', 'desc')->limit(2)->pluck('name')->all());

        // The join does not add columns or rows of its own.
        $product = Product::query()->whereCustom('color', 'red')->orderByCustom('rating')->first();
        $this->assertSame('Apple', $product?->name);
        $this->assertSame(4, Product::query()->orderByCustom('weight')->orderByCustom('rating')->count());
    }

    public function test_facets_count_every_value_with_every_filter_but_its_own(): void
    {
        $facets = CustomFacets::for(Product::class)->counts(['color', 'sizes', 'organic']);

        $this->assertSame(['red' => 2, 'blue' => 1], $facets['color']);
        $this->assertEquals(['m' => 2, 's' => 1, 'l' => 1], $facets['sizes']);
        $this->assertEquals([1 => 1, 0 => 1], $facets['organic']);

        $filters = ['color' => ['red'], 'sizes' => ['m']];
        $facets = CustomFacets::for(Product::class, Product::query()->where('active', true))->filters($filters);

        // Colors are counted among the "m" products, sizes among the red ones.
        $this->assertSame(['blue' => 1, 'red' => 1], $facets->counts(['color'])['color']);
        $this->assertEquals(['s' => 1, 'm' => 1, 'l' => 1], $facets->counts(['sizes'])['sizes']);
        $this->assertSame(['weight' => ['min' => 1.5, 'max' => 1.5]], $facets->ranges(['weight']));
        $this->assertSame(['weight' => ['min' => 0.5, 'max' => 3.0]], CustomFacets::for(Product::class)->ranges(['weight']));
    }

    public function test_a_field_that_is_not_filterable_says_so(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('tick "Filterable"');

        Product::query()->whereCustom('note', 'x')->get();
    }

    public function test_the_index_follows_saves_and_deletes(): void
    {
        $plum = Product::query()->where('name', 'Plum')->firstOrFail();

        $this->fields()->set($plum, 'color', 'green');
        $plum->save();
        $this->assertNames(['Plum'], Product::query()->whereCustom('color', 'green'));

        // A save that does not touch the values leaves the index alone.
        $before = DB::table(Indexer::TABLE)->where('entity_id', $plum->id)->pluck('id')->all();
        $plum->update(['sort' => 10]);
        $this->assertSame($before, DB::table(Indexer::TABLE)->where('entity_id', $plum->id)->pluck('id')->all());

        $plum->delete();
        $this->assertSame(0, DB::table(Indexer::TABLE)->where('entity_id', $plum->id)->count());
        $this->assertNames([], Product::query()->whereCustom('color', 'green'));
    }

    public function test_reindex_rebuilds_the_index(): void
    {
        $count = DB::table(Indexer::TABLE)->count();
        DB::table(Indexer::TABLE)->delete();

        $this->artisan('atrium:custom-fields:reindex', ['module' => Product::class])->assertSuccessful();

        $this->assertSame($count, DB::table(Indexer::TABLE)->count());
        $this->assertNames(['Apple', 'Cherry'], Product::query()->whereCustom('color', 'red'));
    }

    public function test_a_field_becomes_filterable_later(): void
    {
        $this->product('Pear', ['note' => 'juicy']);
        $note = $this->fields()->fieldOrFail(Product::class, 'note');

        $note->update(['filterable' => true]);

        $this->assertNames(['Pear'], Product::query()->whereCustom('note', 'juicy'));

        $note->update(['filterable' => false]);
        $this->assertSame(0, DB::table(Indexer::TABLE)->where('field_id', $note->id)->count());
    }

    public function test_deleting_a_field_drops_its_index_rows(): void
    {
        $color = $this->fields()->fieldOrFail(Product::class, 'color');
        $color->delete();

        $this->assertSame(0, DB::table(Indexer::TABLE)->where('field_id', $color->id)->count());
        $this->assertNull($this->fields()->field(Product::class, 'color'));
    }

    public function test_an_unknown_operator_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator [between] for custom field [weight].');

        Product::query()->whereCustom('weight', 'BETWEEN', 1);
    }

    public function test_the_operator_is_case_insensitive(): void
    {
        $this->assertNames(['Apple'], Product::query()->whereCustom('color', 'LIKE', 'r%')->whereCustom('weight', '<', 2));
    }

    public function test_a_field_in_a_column_of_its_own_is_queried_there(): void
    {
        // Promoted into the products' own "article" column.
        $this->field('sku', 'string', ['filterable' => true, 'searchable' => true])->forceFill(['column' => 'article'])->save();
        DB::table('catalog_products')->where('name', 'Apple')->update(['article' => 'A-1']);
        DB::table('catalog_products')->where('name', 'Plum')->update(['article' => 'P-2']);
        DB::table('catalog_products')->where('name', 'Cherry')->update(['article' => 'C-3']);
        $this->fields()->flush();

        $this->assertNames(['Apple', 'Plum'], Product::query()->whereCustom('sku', ['A-1', 'P-2', '']));
        $this->assertNames(['Cherry', 'Stone'], Product::query()->whereCustom('sku', '!=', ['A-1', 'P-2', '']));
        $this->assertNames(['Plum'], Product::query()->whereCustom('sku', 'P-2'));
        $this->assertNames(['Apple', 'Cherry', 'Stone'], Product::query()->whereCustom('sku', '<>', 'P-2'), 'No value is not that value either.');

        $sku = $this->fields()->fieldOrFail(Product::class, 'sku');
        $this->assertNames(['Plum'], Product::query()->where(fn (Builder $query) => app(CustomQuery::class)->search($query, $sku, 'p-')));

        // The text is lowered in any alphabet, before the database lowers its own.
        DB::table('catalog_products')->where('name', 'Cherry')->update(['article' => 'вишня']);
        $this->assertNames(['Cherry'], Product::query()->where(fn (Builder $query) => app(CustomQuery::class)->search($query, $sku, 'ВИШ')));
    }

    public function test_a_translatable_field_is_searched_in_every_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $this->app->make(ContentLanguages::class)->flush();
        $motto = $this->field('motto', 'string', ['translatable' => true, 'searchable' => true]);
        $apple = Product::query()->where('name', 'Apple')->firstOrFail();
        $this->fields()->set($apple, 'motto', 'Crisp', 'en');
        $this->fields()->set($apple, 'motto', 'Knackig', 'de');
        $apple->save();
        $plum = Product::query()->where('name', 'Plum')->firstOrFail();
        $this->fields()->set($plum, 'motto', 'Sweet', 'en');
        $plum->save();

        $search = fn (string $text) => Product::query()->where(fn (Builder $query) => app(CustomQuery::class)->search($query, $motto, $text));

        $this->assertNames(['Apple'], $search('KNACK'));
        $this->assertNames(['Apple'], $search('crisp'));
        $this->assertNames(['Plum'], $search('swe'));
        $this->assertNames([], $search('sour'));

        $note = $this->fields()->fieldOrFail(Product::class, 'note');
        $this->fields()->set($plum, 'note', 'Juicy');
        $plum->save();
        $this->assertNames(['Plum'], Product::query()->where(fn (Builder $query) => app(CustomQuery::class)->search($query, $note, 'uic')));
    }

    public function test_a_field_without_sorting_says_so(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Custom field [note] cannot be sorted by: tick "Sortable" on it.');

        Product::query()->orderByCustom('note')->get();
    }

    public function test_a_list_of_several_is_never_sorted_by(): void
    {
        $this->fields()->fieldOrFail(Product::class, 'sizes')->forceFill(['sortable' => true])->saveQuietly();
        $this->fields()->flush();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Custom field [sizes] cannot be sorted by');

        Product::query()->orderByCustom('sizes');
    }

    public function test_facets_of_nothing_and_of_a_field_without_an_index(): void
    {
        $nothing = CustomFacets::for(Product::class, Product::query()->where('name', 'Stone'));

        $this->assertSame(['weight' => ['min' => null, 'max' => null], 'released' => ['min' => null, 'max' => null]], $nothing->ranges(['weight', 'released']));
        $this->assertSame(['color' => []], $nothing->counts(['color']));
        $this->assertSame(['released' => ['min' => '2025-12-31', 'max' => '2026-03-01']], CustomFacets::for(Product::class)->ranges(['released']));
        $this->assertSame(['2025-12-31' => 1, '2026-01-10' => 1, '2026-03-01' => 1], CustomFacets::for(Product::class)->counts(['released'])['released']);

        foreach (['counts', 'ranges'] as $method) {
            try {
                CustomFacets::for(Product::class)->{$method}(['note']);
                $this->fail('No facets without an index.');
            } catch (LogicException $e) {
                $this->assertSame('Custom field [note] has no facets: tick "Filterable" on it.', $e->getMessage());
            }
        }
    }

    public function test_deleting_a_record_of_a_module_without_fields_touches_no_index(): void
    {
        $brand = Brand::query()->create(['name' => 'Acme', 'code' => 'acme']);
        DB::enableQueryLog();

        $brand->delete();

        $this->assertSame([], array_values(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], Indexer::TABLE))));
    }

    public function test_a_list_of_several_indexes_only_its_plain_values(): void
    {
        $plum = Product::query()->where('name', 'Plum')->firstOrFail();
        $plum->setAttribute('custom', json_encode(['sizes' => ['m', ['x'], null, 'l']]));
        $plum->save();

        $sizes = $this->fields()->fieldOrFail(Product::class, 'sizes');
        $this->assertSame(['l', 'm'], DB::table(Indexer::TABLE)->where('field_id', $sizes->id)->where('entity_id', $plum->id)->orderBy('value_str')->pluck('value_str')->all());
    }

    public function test_an_empty_filter_is_no_filter(): void
    {
        $everything = ['Apple', 'Cherry', 'Plum', 'Stone'];

        $this->assertNames($everything, Product::query()->whereCustomFilters(['color' => '']));
        $this->assertNames($everything, Product::query()->whereCustomFilters(['color' => []]));
        $this->assertNames($everything, Product::query()->whereCustomFilters(['color' => null]));
    }

    public function test_a_range_filter_may_have_one_end(): void
    {
        $this->assertNames(['Apple', 'Cherry'], Product::query()->whereCustomFilters(['weight' => ['min' => '1,5']]));
        $this->assertNames(['Plum'], Product::query()->whereCustomFilters(['weight' => ['max' => 1]]));
        $this->assertNames(['Apple'], Product::query()->whereCustomFilters(['weight' => ['min' => 1, 'max' => 2]]));
    }

    public function test_each_value_of_a_list_is_read_as_the_field_s_type(): void
    {
        $this->assertNames(['Apple', 'Cherry'], Product::query()->whereCustom('weight', ['1,5', '3', 'heavy']));
        $this->assertNames(['Apple'], Product::query()->whereCustom('released', ['2026-01-10 10:00']));
        $this->assertNames(['Apple'], Product::query()->whereCustom('released', '2026-01-10 10:00'));
        $this->assertNames(['Apple', 'Cherry'], Product::query()->whereCustom('sizes', ['s', 'l']));
    }

    public function test_the_direction_is_case_insensitive(): void
    {
        $this->assertSame(['Cherry', 'Apple', 'Plum', 'Stone'], Product::query()->orderByCustom('weight', 'DESC')->pluck('name')->all());
    }

    public function test_each_sort_joins_the_index_under_a_name_of_its_own(): void
    {
        $weight = $this->fields()->fieldOrFail(Product::class, 'weight')->id;
        $rating = $this->fields()->fieldOrFail(Product::class, 'rating')->id;
        $grammar = DB::connection()->getQueryGrammar();

        $sql = Product::query()->orderByCustom('weight')->orderByCustom('rating')->toSql();

        $this->assertStringContainsString($grammar->wrap(Indexer::TABLE.' as cf_sort_'.$weight.'_1'), $sql);
        $this->assertStringContainsString($grammar->wrap(Indexer::TABLE.' as cf_sort_'.$rating.'_2'), $sql);
    }

    public function test_a_sorted_record_has_only_its_own_columns(): void
    {
        $apple = Product::query()->where('name', 'Apple')->orderByCustom('weight')->firstOrFail();
        $this->assertArrayNotHasKey('field_id', $apple->getAttributes());
        $this->assertSame(Product::query()->where('name', 'Apple')->value('id'), $apple->getKey());

        // A select of its own is kept as it is.
        $apple = Product::query()->select('name')->where('name', 'Apple')->orderByCustom('weight')->firstOrFail();
        $this->assertSame(['name' => 'Apple'], $apple->getAttributes());
    }

    /**
     * @param  array<int,string>  $expected
     */
    private function assertNames(array $expected, Builder $query, string $message = ''): void
    {
        $names = $query->pluck('name')->all();
        sort($names);
        sort($expected);

        $this->assertSame($expected, $names, $message);
    }
}
