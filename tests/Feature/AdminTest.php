<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * The "Fields" screen of a module, and the fields in the module's own form
 * and list.
 */
final class AdminTest extends TestCase
{
    public function test_an_editor_creates_a_field_from_the_modules_fields_screen(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $module = (new Product)->getMorphClass();

        // The products list links to its fields.
        $this->get(route('admin.catalog_products.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('links.0.name', 'custom-fields')
            ->where('links.0.href', route('admin.custom_fields.index', ['module' => $module])));

        // The create form starts with the module of the list it came from.
        $this->get(route('admin.custom_fields.create', ['module' => $module]))
            ->assertInertia(function (AssertableInertia $page) use ($module): void {
                $fields = collect($page->toArray()['props']['fields'])->keyBy('name');
                $this->assertSame($module, $fields['module']['default']);
            });

        $this->post(route('admin.custom_fields.store'), [
            'module' => $module,
            'label' => ['en' => 'Color'],
            'code' => 'color',
            'type' => 'select',
            'options' => ['en' => "red = Red\nblue = Blue"],
            'tab' => 'params',
            'sort' => 10,
            'filterable' => true,
            'show_in_list' => true,
        ])->assertSessionDoesntHaveErrors();

        $field = CustomField::query()->sole();
        $this->assertSame('color', $field->code);
        $this->assertSame(['red' => 'Red', 'blue' => 'Blue'], $field->optionList());

        // Only this module's fields on its screen.
        CustomField::query()->create(['module' => 'other', 'code' => 'x', 'type' => 'string', 'label' => ['en' => 'X']]);
        $this->get(route('admin.custom_fields.index', ['module' => $module]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.module', $module)
            ->where('items.total', 1)
            ->where('items.data.0.code', 'color'));
    }

    public function test_the_definition_is_checked(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $module = (new Product)->getMorphClass();
        $post = fn (array $data) => $this->post(route('admin.custom_fields.store'), [
            'module' => $module, 'label' => ['en' => 'Field'], 'type' => 'string', 'tab' => 'params', 'sort' => 500, ...$data,
        ]);

        $post(['code' => 'Bad code'])->assertSessionHasErrors('code');
        $post(['code' => 'price'])->assertSessionHasErrors('code');      // a column of the table
        $post(['code' => 'custom'])->assertSessionHasErrors('code');     // the values' own column
        $post(['code' => 'size', 'type' => 'select'])->assertSessionHasErrors('options');
        $post(['code' => 'about', 'type' => 'text', 'filterable' => true])->assertSessionHasErrors('filterable');
        $post(['code' => 'tags', 'type' => 'multiselect', 'options' => ['en' => 'a'], 'sortable' => true])->assertSessionHasErrors('sortable');
        $post(['code' => 'maker', 'type' => 'link'])->assertSessionHasErrors('link_module');
        $post(['module' => 'nope', 'code' => 'ok'])->assertSessionHasErrors('module');
        $post(['code' => 'size', 'type' => 'number', 'translatable' => true])->assertSessionHasErrors('translatable');
        $post(['code' => 'size', 'type' => 'number', 'searchable' => true])->assertSessionHasErrors('searchable');
        $post(['code' => 'size', 'searchable' => true, 'per_site' => true])->assertSessionHasErrors('searchable');
        $this->assertSame(0, CustomField::query()->count());

        $post(['code' => 'weight', 'type' => 'decimal'])->assertSessionDoesntHaveErrors();
        $post(['code' => 'weight'])->assertSessionHasErrors('code');

        // Code and type are fixed: the records keep their values under them.
        $field = CustomField::query()->sole();
        $this->put(route('admin.custom_fields.update', $field), [
            'module' => $module, 'label' => ['en' => 'Weight'], 'code' => 'mass', 'type' => 'decimal', 'tab' => 'params', 'sort' => 500,
        ])->assertSessionHasErrors('code');
        $this->assertSame('weight', $field->fresh()?->code);
    }

    public function test_fields_are_edited_in_the_modules_form_and_shown_in_its_list(): void
    {
        $this->field('color', 'select', ['options' => ['en' => "red = Red\nblue = Blue"], 'filterable' => true, 'show_in_list' => true, 'tab' => 'detail', 'sort' => 5]);
        $this->field('weight', 'decimal', ['sortable' => true, 'show_in_list' => true]);
        $this->field('organic', 'boolean');
        $this->actingAs($this->admin(), 'admin');

        $this->get(route('admin.catalog_products.create'))->assertInertia(function (AssertableInertia $page): void {
            $fields = collect($page->toArray()['props']['fields'])->keyBy('name');
            $this->assertSame('select', $fields['color']['type']);
            $this->assertSame('detail', $fields['color']['tab']);
            $this->assertSame('Color', $fields['color']['label']);
            $this->assertArrayHasKey('weight', $fields->all());
            $this->assertContains('color', array_column($page->toArray()['props']['fieldsByTab']['detail'], 'name'));
        });

        $this->post(route('admin.catalog_products.store'), [
            'name' => 'Apple',
            'active' => true,
            'sort' => 500,
            'color' => 'red',
            'weight' => '1.25',
            'organic' => true,
        ])->assertSessionDoesntHaveErrors();

        $apple = Product::query()->where('name', 'Apple')->sole();
        $this->assertEquals(['color' => 'red', 'weight' => 1.25, 'organic' => true], $this->fields()->stored($apple));
        $this->assertSame(2, DB::table(Indexer::TABLE)->where('entity_id', $apple->id)->count()); // color and weight

        $this->get(route('admin.catalog_products.edit', $apple))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('item.color', 'red')
            ->where('item.weight', 1.25)
            ->where('item.organic', true)
            ->missing('item.custom'));

        $this->product('Plum', ['color' => 'blue', 'weight' => 0.5]);

        $this->get(route('admin.catalog_products.index', ['sort' => 'weight', 'direction' => 'asc']))
            ->assertInertia(function (AssertableInertia $page): void {
                $props = $page->toArray()['props'];
                $this->assertContains('color', array_column($props['columns'], 'name'));
                $this->assertSame(['Plum', 'Apple'], array_column($props['items']['data'], 'name'));
                $this->assertSame(['Blue', 'Red'], array_column($props['items']['data'], 'color'));
            });
    }

    public function test_a_field_is_edited_under_its_own_code(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $module = (new Product)->getMorphClass();
        $field = $this->field('motto', 'string');
        $put = fn (array $data) => $this->put(route('admin.custom_fields.update', $field), [
            'module' => $module, 'label' => ['en' => 'Motto'], 'code' => 'motto', 'type' => 'string', 'tab' => 'params', 'sort' => 500, ...$data,
        ]);

        $this->get(route('admin.custom_fields.edit', $field))->assertInertia(fn (AssertableInertia $page) => $page->where('itemTitle', 'Motto'));

        $put(['label' => ['en' => 'Slogan'], 'translatable' => true, 'searchable' => true])->assertSessionDoesntHaveErrors();
        $this->assertSame('Slogan', $field->fresh()?->labelText());

        // Promoted into a column: one value for every language and site.
        $field->refresh()->forceFill(['column' => 'motto', 'translatable' => false])->save();
        $put(['translatable' => true])->assertSessionHasErrors(['translatable' => __('atrium-custom-fields::custom-fields.errors.promoted', ['column' => 'motto'])]);
        $put(['per_site' => true])->assertSessionHasErrors('per_site');
        $put(['label' => ['en' => 'Words']])->assertSessionDoesntHaveErrors();
    }
}
