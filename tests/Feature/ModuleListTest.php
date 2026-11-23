<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Brands\Models\Brand;
use Atrium\Catalog\Models\CatalogSection;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Support\ModuleList;
use Atrium\CustomFields\Tests\Fixtures\Flyer;
use Atrium\CustomFields\Tests\Fixtures\Leaflet;
use Atrium\CustomFields\Tests\Fixtures\Pamphlet;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Which modules can get fields, and how a module is named.
 */
final class ModuleListTest extends TestCase
{
    public function test_every_content_module_but_the_fields_screen_and_the_services(): void
    {
        $models = $this->modules()->models();

        $this->assertSame([
            Site::class => Site::class,
            Product::class => Product::class,
            CatalogSection::class => CatalogSection::class,
            Brand::class => Brand::class,
        ], $models);
        $this->assertNotContains(CustomField::class, $models);
        $this->assertNotContains(Language::class, $models, 'languages are the panel\'s own record, not content');
        $this->assertSame([Brand::class => Brand::class], $this->with(['custom_fields' => CustomField::class, 'brands' => Brand::class])->models(), 'the modules after it too');
    }

    public function test_the_fields_screen_says_itself_that_it_is_no_content(): void
    {
        $method = new ReflectionMethod(CustomField::class, 'isContentModule');

        $this->assertTrue($method->isPublic(), 'the panel asks it from outside the model');
        $this->assertFalse($method->invoke(null));
    }

    public function test_a_module_is_keyed_by_its_morph_alias(): void
    {
        Relation::morphMap(['product' => Product::class]);

        try {
            $this->assertSame(Product::class, $this->modules()->models()['product']);
            $this->assertSame('product', $this->modules()->morphOf(Product::class));
            $this->assertSame(Product::class, $this->modules()->modelClass('product'));
        } finally {
            Relation::morphMap([], false);
        }
    }

    public function test_the_options_are_sorted_by_label(): void
    {
        $this->assertSame([
            Brand::class => 'Brands',
            CatalogSection::class => 'Catalog sections',
            Product::class => 'Products',
            Site::class => 'Sites',
        ], $this->modules()->options());
    }

    public function test_a_label_names_the_module_or_repeats_what_is_unknown(): void
    {
        $this->assertSame('Products', $this->modules()->label(Product::class));
        $this->assertSame('App\\Gone', $this->modules()->label('App\\Gone'));
    }

    public function test_only_an_administrable_model_is_a_module(): void
    {
        $modules = $this->modules();

        $this->assertSame(Brand::class, $modules->modelClass(Brand::class));
        $this->assertNull($modules->modelClass(Leaflet::class), 'a model the admin does not know');
        $this->assertNull($modules->modelClass(ModuleList::class), 'not a model');
        $this->assertNull($modules->modelClass(Pamphlet::class), 'an admin module that is no model');
        $this->assertNull($modules->modelClass('App\\Gone'), 'no such class');
        $this->assertNull($modules->modelClass(''));
    }

    public function test_a_module_is_found_by_class_or_admin_key(): void
    {
        $modules = $this->modules();

        $this->assertSame(Product::class, $modules->find(Product::class));
        $this->assertSame(Product::class, $modules->find('catalog_products'));
        $this->assertSame(Brand::class, $modules->find('brands'));
        $this->assertNull($modules->find('nope'));
        $this->assertNull($modules->find(Leaflet::class));
        $this->assertSame(Product::class, $this->with([Brand::class => Product::class])->find(Brand::class), 'a key wins over a class');
    }

    public function test_the_morph_of_an_instance_a_class_or_a_key(): void
    {
        $modules = $this->modules();

        $this->assertSame(Product::class, $modules->morphOf(new Product));
        $this->assertSame(Product::class, $modules->morphOf(Product::class));
        $this->assertSame(Product::class, $modules->morphOf('catalog_products'));
    }

    public function test_the_morph_of_something_else_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[nope] is not a model or an admin module.');

        $this->modules()->morphOf('nope');
    }

    public function test_the_tabs_of_a_form(): void
    {
        $modules = $this->modules();

        $this->assertSame(
            ['cover' => 'Cover page', 'inside' => 'inside', 'back' => 'back', 'params' => 'Parameters'],
            $modules->tabsOf(Leaflet::class),
            'nameless tabs are skipped',
        );
        $this->assertSame(['params', 'preview', 'detail', 'seo', 'sections'], array_keys($modules->tabsOf(Product::class)));
        $this->assertSame([], $modules->tabsOf(Flyer::class), 'a form that switched its tabs off');
        $this->assertSame([], $modules->tabsOf(ModuleList::class), 'no form at all');
    }

    public function test_the_tab_select_names_each_tab_once(): void
    {
        $tabs = $this->modules()->tabOptions();

        $this->assertSame('Site (params)', $tabs['params'], 'the first module that has the tab names it');
        $this->assertSame('Preview (preview)', $tabs['preview']);
        $this->assertSame(['params', 'codes', 'robots', 'preview', 'detail', 'seo', 'sections'], array_keys($tabs));
    }

    public function test_a_tab_named_as_its_label_is_not_repeated(): void
    {
        $tabs = $this->with([Leaflet::class])->tabOptions();

        $this->assertSame(['cover' => 'Cover page (cover)', 'inside' => 'inside', 'back' => 'back', 'params' => 'Parameters (params)'], $tabs);
    }

    public function test_the_tab_select_offers_params_when_no_module_has_tabs(): void
    {
        $this->assertSame(['params' => 'params'], $this->with([])->tabOptions());
        $this->assertSame(['params' => 'params'], $this->with([Flyer::class])->tabOptions());
    }

    public function test_the_names_a_new_field_may_not_take(): void
    {
        $taken = $this->modules()->takenNames(Product::class);

        foreach (['id', 'custom', 'admin_url', 'public_url', 'fullPath'] as $reserved) {
            $this->assertContains($reserved, $taken);
        }

        $this->assertContains('price', $taken, 'a column of the table');
        $this->assertContains('preview_text_type', $taken, 'a column the form does not show');
        $this->assertContains('related', $taken, 'a field of the form that is no column');
        $this->assertContains('seo_title', $taken, 'a field of the SEO tab');
        $this->assertNotContains('weight', $taken);

        $this->assertNotContains('seo_title', $this->modules()->takenNames(Language::class), 'a module without SEO');
        $this->assertContains('code', $this->modules()->takenNames(Language::class));
    }

    private function modules(): ModuleList
    {
        return app(ModuleList::class);
    }

    /**
     * A module list over the given models only.
     *
     * @param  array<int|string,class-string>  $models
     */
    private function with(array $models): ModuleList
    {
        $registry = new ResourceRegistry;
        (new ReflectionProperty($registry, 'resources'))->setValue($registry, $models);

        return new ModuleList($registry);
    }
}
