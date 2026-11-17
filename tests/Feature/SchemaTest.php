<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Brands\Models\Brand;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;

/**
 * The tables custom fields need: the module's JSON column comes with its
 * first field, and nothing breaks before the package's own migrations ran.
 */
final class SchemaTest extends TestCase
{
    /**
     * Columns and tables change here: DDL ends a transaction on MySQL, so
     * every test migrates afresh instead.
     *
     * @var array<int,string>
     */
    protected $connectionsToTransact = [];

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::setUp();
    }

    public function test_the_first_field_of_a_module_adds_its_json_column(): void
    {
        $this->assertFalse(Schema::hasColumn('brands', 'custom'));

        CustomField::query()->create(['module' => (new Brand)->getMorphClass(), 'code' => 'origin', 'type' => 'string', 'label' => ['en' => 'Origin']]);
        $this->assertTrue(Schema::hasColumn('brands', 'custom'));

        // The second one finds it there.
        CustomField::query()->create(['module' => (new Brand)->getMorphClass(), 'code' => 'founded', 'type' => 'number', 'label' => ['en' => 'Founded']]);

        $brand = Brand::query()->create(['name' => 'Acme', 'code' => 'acme']);
        $this->fields()->set($brand, 'origin', 'Minsk');
        $brand->save();
        $this->assertSame('Minsk', $this->fields()->get($brand->fresh() ?? $brand, 'origin'));
    }

    public function test_nothing_breaks_before_the_tables_are_there(): void
    {
        Schema::drop(Indexer::TABLE);
        Schema::drop('custom_fields');
        $this->app->forgetInstance(CustomFields::class);

        $fields = $this->app->make(CustomFields::class);
        $this->assertFalse($fields->isInstalled());
        $this->assertSame([], $fields->all(Product::class));
        $this->assertFalse($fields->hasFieldsFor(new Product));

        $product = Product::query()->create(['name' => 'Apple', 'active' => true]);
        $product->delete();
        $this->assertNull(Product::query()->find($product->id));
    }

    public function test_without_a_site_the_values_belong_to_every_site(): void
    {
        $site = Site::query()->first() ?? Site::query()->create(['code' => 'main', 'name' => 'Main', 'active' => true, 'is_default' => true]);
        $this->app->forgetInstance(Sites::class);
        $this->assertSame($site->id, $this->fields()->site());
        $this->assertGreaterThan(0, $site->id);

        // A mass delete fires no model event: the kept list of sites is dropped by hand.
        Site::query()->delete();
        $this->app->make(Sites::class)->flush();
        $this->app->forgetInstance(Sites::class);

        $this->assertSame(0, $this->fields()->site());
    }
}
