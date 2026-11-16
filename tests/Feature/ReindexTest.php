<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Brands\Models\Brand;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Jobs\ReindexField;
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilding the filter index: every module, one module, one field.
 */
final class ReindexTest extends TestCase
{
    public function test_without_a_module_every_module_with_fields_is_rebuilt(): void
    {
        $this->field('color', 'string', ['filterable' => true]);
        $this->product('Apple', ['color' => 'red']);
        $this->product('Plum', ['color' => 'blue']);
        DB::table(Indexer::TABLE)->delete();

        $this->artisan('atrium:custom-fields:reindex')
            ->expectsOutput(Product::class.': 2 records')
            ->doesntExpectOutputToContain(Brand::class)
            ->assertSuccessful();

        $this->assertSame(2, DB::table(Indexer::TABLE)->count());
    }

    public function test_the_definitions_are_read_afresh(): void
    {
        $color = $this->field('color', 'string');
        $this->product('Apple', ['color' => 'red']);
        $this->assertFalse($this->fields()->field(Product::class, 'color')?->isIndexed());

        // Changed behind this process's back: by a deploy, or by hand.
        CustomField::query()->whereKey($color->id)->update(['filterable' => true]);

        $this->artisan('atrium:custom-fields:reindex', ['module' => Product::class])->assertSuccessful();

        $this->assertSame(1, DB::table(Indexer::TABLE)->where('field_id', $color->id)->count());
    }

    public function test_a_module_is_named_by_its_class_or_its_admin_key(): void
    {
        $this->field('color', 'string', ['filterable' => true]);
        $this->product('Apple', ['color' => 'red']);
        DB::table(Indexer::TABLE)->delete();

        $this->artisan('atrium:custom-fields:reindex', ['module' => 'catalog_products'])
            ->expectsOutput(Product::class.': 1 records')
            ->assertSuccessful();
        $this->assertSame(1, DB::table(Indexer::TABLE)->count());

        $this->artisan('atrium:custom-fields:reindex', ['module' => 'nope'])
            ->expectsOutput('Unknown module [nope].')
            ->assertFailed();
    }

    public function test_one_field_is_rebuilt_alone(): void
    {
        $color = $this->field('color', 'string', ['filterable' => true]);
        $size = $this->field('size', 'number', ['filterable' => true]);
        $this->product('Apple', ['color' => 'red', 'size' => 3]);
        DB::table(Indexer::TABLE)->delete();

        $this->artisan('atrium:custom-fields:reindex', ['module' => Product::class, '--field' => 'size'])->assertSuccessful();

        $this->assertSame([$size->id], DB::table(Indexer::TABLE)->pluck('field_id')->all());
        $this->assertNotSame($color->id, $size->id);

        $this->artisan('atrium:custom-fields:reindex', ['module' => Product::class, '--field' => 'height'])
            ->expectsOutput('No custom field [height] in ['.Product::class.'].')
            ->assertFailed();
    }

    public function test_the_job_of_a_field_that_is_gone_does_nothing(): void
    {
        $color = $this->field('color', 'string', ['filterable' => true]);
        $this->product('Apple', ['color' => 'red']);
        DB::table(Indexer::TABLE)->delete();

        ReindexField::dispatchSync($color->id + 1000);
        $this->assertSame(0, DB::table(Indexer::TABLE)->count());

        // A module that is not installed any more.
        CustomField::query()->whereKey($color->id)->update(['module' => 'gone']);
        ReindexField::dispatchSync($color->id);
        $this->assertSame(0, DB::table(Indexer::TABLE)->count());

        CustomField::query()->whereKey($color->id)->update(['module' => (new Product)->getMorphClass()]);
        ReindexField::dispatchSync($color->id);
        $this->assertSame(1, DB::table(Indexer::TABLE)->count());
    }
}
