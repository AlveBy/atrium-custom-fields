<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * The rows of the filter index: what goes in, and how few queries it takes.
 */
final class IndexerTest extends TestCase
{
    /** @var array<int,string> */
    private array $queries = [];

    public function test_a_record_without_indexed_fields_touches_no_index(): void
    {
        $this->field('note', 'string');
        $this->listen();

        $this->product('Apple', ['note' => 'Crisp']);

        $this->assertSame([], $this->queries(Indexer::TABLE));
    }

    public function test_a_rebuild_without_indexed_fields_reads_no_records(): void
    {
        $this->field('note', 'string');
        $this->product('Apple', ['note' => 'Crisp']);

        $this->assertSame(0, $this->indexer()->reindex(Product::class));
    }

    public function test_a_rebuild_counts_every_record_and_writes_in_batches(): void
    {
        $this->field('color', 'string', ['filterable' => true]);
        $rows = [];

        for ($i = 1; $i <= 501; $i++) {
            $rows[] = ['name' => 'Product '.$i, 'active' => true, 'custom' => '{"color":"red"}'];
        }

        DB::table('catalog_products')->insert($rows);
        $this->listen();

        $this->assertSame(501, $this->indexer()->reindex(Product::class));
        $this->assertSame(501, DB::table(Indexer::TABLE)->count());
        $this->assertCount(2, array_filter($this->queries(Indexer::TABLE), static fn (string $sql): bool => str_starts_with($sql, 'insert')), 'a batch per 500 records');
    }

    public function test_the_languages_are_read_once_per_rebuild(): void
    {
        $this->field('motto', 'string', ['translatable' => true, 'filterable' => true]);
        $this->product('Apple', ['motto' => 'Crisp']);
        $this->product('Plum', ['motto' => 'Sweet']);
        $this->fields()->defaultLocale();
        $this->listen();

        $this->indexer()->reindex(Product::class);

        $this->assertCount(1, $this->queries((new Language)->getTable()));
    }

    public function test_every_language_gets_a_row_and_so_does_one_off_the_list(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        Language::query()->create(['code' => 'pl', 'name' => 'Polski', 'sort' => 300]);
        $this->app->make(ContentLanguages::class)->flush();
        $motto = $this->field('motto', 'string', ['translatable' => true, 'filterable' => true]);

        // French is no language of the site (yet): its text is indexed all the same.
        $apple = $this->product('Apple', ['motto' => ['en' => 'Crisp', 'fr' => 'Croquant']]);
        $plum = $this->product('Plum', ['motto' => ['fr' => 'Sucré']]);

        $this->assertSame(['de' => 'Crisp', 'en' => 'Crisp', 'fr' => 'Croquant', 'pl' => 'Crisp'], $this->rows($apple->id, $motto->id));
        $this->assertSame(['fr' => 'Sucré'], $this->rows($plum->id, $motto->id), 'no text in the default language: nothing to fall back to');
    }

    public function test_a_language_code_stays_text_where_php_makes_it_a_number(): void
    {
        $motto = $this->field('motto', 'string', ['translatable' => true, 'filterable' => true]);
        $apple = $this->raw('Apple', ['motto' => ['1' => 'One']]);

        $this->assertSame(['1' => 'One'], $this->rows($apple->id, $motto->id));
    }

    public function test_a_list_stored_as_one_value_is_a_list_of_one(): void
    {
        $sizes = $this->field('sizes', 'multiselect', ['options' => ['en' => "s\nm\n7"], 'filterable' => true]);
        $apple = $this->raw('Apple', ['sizes' => 'm']);
        $plum = $this->raw('Plum', ['sizes' => [7, true]]);

        $this->assertSame(['' => 'm'], $this->rows($apple->id, $sizes->id));
        $this->assertSame(['1', '7'], DB::table(Indexer::TABLE)->where('entity_id', $plum->id)->orderBy('value_str')->pluck('value_str')->all());
    }

    public function test_a_long_text_is_cut_to_the_index_column_in_characters(): void
    {
        $title = $this->field('title', 'string', ['filterable' => true]);
        $apple = $this->product('Apple', ['title' => str_repeat('й', 200)]);

        $this->assertSame(['' => str_repeat('й', 191)], $this->rows($apple->id, $title->id));
    }

    /**
     * A product saved with the JSON as given, as an import writes it.
     *
     * @param  array<string,mixed>  $custom
     */
    private function raw(string $name, array $custom): Product
    {
        $product = new Product(['name' => $name, 'active' => true]);
        $product->setAttribute('custom', json_encode($custom));
        $product->save();

        return $product;
    }

    private function indexer(): Indexer
    {
        return $this->app->make(Indexer::class);
    }

    private function listen(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            $this->queries[] = $query->sql;
        });
    }

    /**
     * @return array<int,string>
     */
    private function queries(string $containing): array
    {
        return array_values(array_filter($this->queries, static fn (string $sql): bool => str_contains($sql, $containing)));
    }

    /**
     * @return array<string,string>
     */
    private function rows(int $entity, int $field): array
    {
        return DB::table(Indexer::TABLE)->where('entity_id', $entity)->where('field_id', $field)
            ->orderBy('locale')->pluck('value_str', 'locale')->all();
    }
}
