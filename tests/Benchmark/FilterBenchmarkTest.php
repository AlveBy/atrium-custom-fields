<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Benchmark;

use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\CustomFacets;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

/**
 * A shop's filter panel over many products: the timings for the README.
 * Skipped unless asked for:
 *
 *     CUSTOM_FIELDS_BENCHMARK=100000 vendor/bin/phpunit packages/custom-fields/tests/Benchmark
 *
 * (with DB_CONNECTION=mysql or pgsql and their DB_* for those databases).
 */
final class FilterBenchmarkTest extends TestCase
{
    private const COLORS = ['red', 'blue', 'green', 'black', 'white', 'grey', 'yellow', 'pink', 'brown', 'orange'];

    private const SIZES = ['xs', 's', 'm', 'l', 'xl'];

    private const RUNS = 7;

    /** @var array<int,string> */
    protected $connectionsToTransact = [];

    protected function setUp(): void
    {
        if ((int) env('CUSTOM_FIELDS_BENCHMARK', 0) <= 0) {
            $this->markTestSkipped('Set CUSTOM_FIELDS_BENCHMARK to the number of products.');
        }

        RefreshDatabaseState::$migrated = false;

        parent::setUp();
    }

    public function test_filter_and_facets(): void
    {
        $count = (int) env('CUSTOM_FIELDS_BENCHMARK');
        $this->field('color', 'select', ['options' => ['en' => implode("\n", self::COLORS)], 'filterable' => true]);
        $this->field('sizes', 'multiselect', ['options' => ['en' => implode("\n", self::SIZES)], 'filterable' => true]);
        $this->field('weight', 'decimal', ['filterable' => true, 'sortable' => true]);
        $this->field('rating', 'number', ['sortable' => true]);

        $started = microtime(true);
        $this->seedProducts($count);
        $seeded = microtime(true) - $started;

        $started = microtime(true);
        $this->artisan('atrium:custom-fields:reindex', ['module' => Product::class])->assertSuccessful();
        $reindexed = microtime(true) - $started;

        // Fresh statistics, as the database keeps them on its own a few
        // seconds after a bulk change (MySQL's are from the empty table).
        $analyze = DB::connection()->getDriverName() === 'mysql' ? 'analyze table ' : 'analyze ';
        DB::statement($analyze.DB::getQueryGrammar()->wrapTable(Indexer::TABLE));

        $filters = ['color' => ['red', 'blue'], 'sizes' => ['m'], 'weight' => ['min' => 1, 'max' => 5]];

        $list = $this->median(function () use ($filters): void {
            Product::query()->where('active', true)->whereCustomFilters($filters)
                ->orderByCustom('rating', 'desc')->paginate(24);
        });

        $facets = $this->median(function () use ($filters): void {
            $facets = CustomFacets::for(Product::class, Product::query()->where('active', true))->filters($filters);
            $facets->counts(['color', 'sizes']);
            $facets->ranges(['weight']);
        });

        $total = Product::query()->where('active', true)->whereCustomFilters($filters)->count();

        fwrite(STDERR, sprintf(
            "\n%s, %d products, %d index rows: seed %.1f s, reindex %.1f s; filtered list (%d found, page of 24 by rating) %.1f ms, facets (2 counts + 1 range) %.1f ms\n",
            DB::connection()->getDriverName(),
            $count,
            DB::table(Indexer::TABLE)->count(),
            $seeded,
            $reindexed,
            $total,
            $list * 1000,
            $facets * 1000,
        ));

        $this->assertGreaterThan(0, $total);
    }

    private function seedProducts(int $count): void
    {
        mt_srand(42);
        $now = now()->toDateTimeString();

        for ($offset = 0; $offset < $count; $offset += 1000) {
            $rows = [];

            for ($i = $offset; $i < min($count, $offset + 1000); $i++) {
                $sizes = array_values(array_unique([self::SIZES[mt_rand(0, 4)], self::SIZES[mt_rand(0, 4)]]));
                $rows[] = [
                    'name' => 'Product '.$i,
                    'code' => 'product-'.$i,
                    'active' => mt_rand(0, 9) > 0,
                    'sort' => 500,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'custom' => json_encode([
                        'color' => self::COLORS[mt_rand(0, 9)],
                        'sizes' => $sizes,
                        'weight' => mt_rand(10, 1000) / 100,
                        'rating' => mt_rand(1, 5),
                    ]),
                ];
            }

            DB::table('catalog_products')->insert($rows);
        }
    }

    private function median(callable $run): float
    {
        $run(); // warm up
        $times = [];

        for ($i = 0; $i < self::RUNS; $i++) {
            $started = microtime(true);
            $run();
            $times[] = microtime(true) - $started;
        }

        sort($times);

        return $times[intdiv(self::RUNS, 2)];
    }
}
