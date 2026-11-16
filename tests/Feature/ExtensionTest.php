<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Brands\Models\Brand;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\Admin\CustomFieldsExtension;
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the admin's hooks get from custom fields: the form's fields, the
 * list's columns, the saved data and the values shown.
 */
final class ExtensionTest extends TestCase
{
    /**
     * @return array<string,array{string,array<string,mixed>,array<string,mixed>}>
     */
    public static function formFields(): array
    {
        return [
            'a line of text' => ['string', [], ['type' => 'text', 'maxLength' => 255]],
            'a text' => ['text', [], ['type' => 'textarea']],
            'a number' => ['number', [], ['type' => 'number', 'decimals' => 0]],
            'a decimal' => ['decimal', [], ['type' => 'number', 'step' => 0.01, 'decimals' => 2]],
            'yes or no' => ['boolean', [], ['type' => 'checkbox']],
            'a date' => ['date', [], ['type' => 'date']],
            'a list' => ['select', ['options' => ['en' => "s = Small\nm = Medium"]], ['type' => 'select', 'options' => [['value' => 's', 'label' => 'Small'], ['value' => 'm', 'label' => 'Medium']]]],
            'a list of several' => ['multiselect', ['options' => ['en' => "s = Small\nm = Medium"]], ['type' => 'multiSelect', 'options' => [['value' => 's', 'label' => 'Small'], ['value' => 'm', 'label' => 'Medium']]]],
            'a link' => ['link', ['link_module' => Brand::class], ['type' => 'belongsTo', 'modelClass' => Brand::class]],
            'a file' => ['file', [], ['type' => 'file']],
            'an image' => ['image', [], ['type' => 'image']],
        ];
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @param  array<string,mixed>  $expected
     */
    #[DataProvider('formFields')]
    public function test_every_type_gets_its_form_field(string $type, array $attributes, array $expected): void
    {
        $this->field('spec', $type, ['required' => true, 'tab' => 'detail', 'sort' => 30, ...$attributes]);

        $fields = $this->extension()->fields(Product::class);

        $this->assertCount(1, $fields);
        $field = $fields[0]->toArray();
        $this->assertSame('spec', $field['name']);
        $this->assertSame('Spec', $field['label']);
        $this->assertTrue($field['required']);
        $this->assertSame('detail', $field['tab']);
        $this->assertSame(30, $field['tabOrder']);
        $this->assertNull($field['hint']);
        $this->assertSame($expected, array_intersect_key($field, $expected));
    }

    public function test_the_field_s_hint_order_and_tab(): void
    {
        $this->field('weight', 'decimal', ['hint' => ['en' => 'In kilograms'], 'tab' => 'nope', 'sort' => 0]);

        $field = $this->extension()->fields(Product::class)[0]->toArray();
        $this->assertSame('In kilograms', $field['hint']);
        $this->assertFalse($field['required']);
        $this->assertSame('params', $field['tab'], 'A tab the form lacks: the first one.');
        $this->assertSame(1, $field['tabOrder']);

        // A form without tabs keeps the field's own.
        CustomField::query()->create(['module' => (new Brand)->getMorphClass(), 'code' => 'origin', 'type' => 'string', 'label' => ['en' => 'Origin'], 'tab' => 'nope']);
        $this->fields()->flush();
        $this->assertSame('nope', $this->extension()->fields(Brand::class)[0]->toArray()['tab']);
    }

    public function test_the_definitions_screen_gets_nothing_of_its_own(): void
    {
        $this->field('weight', 'decimal', ['show_in_list' => true, 'translatable' => false]);
        // Even a definition written by hand for the screen itself.
        DB::table('custom_fields')->insert([
            'module' => (new CustomField)->getMorphClass(), 'code' => 'x', 'type' => 'string', 'label' => '{"en":"X"}',
            'show_in_list' => true, 'translatable' => true, 'filterable' => true, 'sortable' => true, 'searchable' => true,
        ]);

        $this->assertSame([], $this->extension()->fields(CustomField::class));
        $this->assertSame([], $this->extension()->columns(CustomField::class));
        $this->assertSame([], $this->extension()->translatable(CustomField::class));
        $this->assertSame([], $this->extension()->links(CustomField::class));
        $this->assertSame([], $this->extension()->fields(Brand::class));

        $query = CustomField::query();
        $this->assertFalse(Hook::apply('atrium.query.filter', false, $query, 'x', 'y'));
        $this->assertFalse(Hook::apply('atrium.query.sort', false, $query, 'x', 'asc'));
        $this->assertFalse(Hook::apply('atrium.query.search', false, $query, 'x', 'y'));
    }

    public function test_the_hooks_add_to_what_the_module_has(): void
    {
        $this->field('motto', 'string', ['translatable' => true, 'show_in_list' => true]);
        $this->fields()->flush();

        $fields = Hook::apply('atrium.module.fields', ['first', 'second'], Product::class);
        $this->assertSame(['first', 'second'], array_slice($fields, 0, 2));
        $this->assertCount(3, $fields);

        $columns = Hook::apply('atrium.module.columns', ['first', 'second'], Product::class);
        $this->assertSame(['first', 'second'], array_slice($columns, 0, 2));
        $this->assertCount(3, $columns);

        $this->assertSame(['name', 'code', 'motto'], Hook::apply('atrium.module.translatable', ['name', 'code'], Product::class));
        $this->assertSame([['name' => 'first'], ['name' => 'second']], Hook::apply('atrium.module.links', [['name' => 'first'], ['name' => 'second']], Product::class), 'nobody signed in: no button of its own');
    }

    public function test_the_query_hooks_say_whether_they_took_the_name(): void
    {
        $this->field('color', 'select', ['options' => ['en' => "red\nblue"], 'filterable' => true]);
        $this->field('weight', 'decimal', ['filterable' => true, 'sortable' => true]);
        $this->field('title', 'string', ['searchable' => true]);
        $this->field('note', 'string');
        $this->fields()->flush();

        foreach (['filter' => 'red', 'sort' => 'asc', 'search' => 'r'] as $hook => $argument) {
            $name = ['filter' => 'color', 'sort' => 'weight', 'search' => 'title'][$hook];

            $this->assertTrue(Hook::apply('atrium.query.'.$hook, false, Product::query(), $name, $argument), $hook.': a field of its own');
            $this->assertFalse(Hook::apply('atrium.query.'.$hook, false, Product::query(), 'note', $argument), $hook.': a field that cannot');
            $this->assertFalse(Hook::apply('atrium.query.'.$hook, false, Product::query(), 'nope', $argument), $hook.': no such field');
            $this->assertTrue(Hook::apply('atrium.query.'.$hook, true, Product::query(), 'nope', $argument), $hook.': taken before');
        }
    }

    public function test_the_filter_hook_reads_a_list_a_range_and_a_column(): void
    {
        $this->field('color', 'select', ['options' => ['en' => "red\nblue\ngreen"], 'filterable' => true]);
        $this->field('weight', 'decimal', ['filterable' => true]);
        // Promoted, never ticked "Filterable": its column is filtered all the same.
        $this->field('sku', 'string')->forceFill(['column' => 'article'])->save();
        $this->product('Apple', ['color' => 'red', 'weight' => 1.5]);
        $this->product('Plum', ['color' => 'blue', 'weight' => 0.5]);
        $this->product('Lime', ['color' => 'green', 'weight' => 3]);
        DB::table('catalog_products')->where('name', 'Lime')->update(['article' => 'L-1']);
        $this->fields()->flush();

        $names = static function (string $name, mixed $value): array {
            $query = Product::query();
            Hook::apply('atrium.query.filter', false, $query, $name, $value);

            return $query->orderBy('name')->pluck('name')->all();
        };

        $this->assertSame(['Apple', 'Plum'], $names('color', ['red', 'blue']));
        $this->assertSame(['Apple', 'Plum'], $names('color', 'red,blue'));
        $this->assertSame(['Apple', 'Plum'], $names('weight', ['max' => 2]));
        $this->assertSame(['Apple', 'Lime'], $names('weight', ['min' => 1]));
        $this->assertSame(['Lime'], $names('sku', 'L-1'));
    }

    public function test_a_list_column_sorts_only_by_a_field_ticked_sortable(): void
    {
        $this->field('weight', 'decimal', ['show_in_list' => true, 'filterable' => true]);

        $columns = array_map(static fn ($column): array => $column->toArray(), $this->extension()->columns(Product::class));

        $this->assertSame([false], array_column($columns, 'sortable'), 'in the index, but not to sort by');
        $this->assertFalse(Hook::apply('atrium.query.sort', false, Product::query(), 'weight', 'asc'));
    }

    public function test_the_list_shows_the_fields_ticked_for_it(): void
    {
        $this->field('weight', 'decimal', ['show_in_list' => true, 'sortable' => true, 'searchable' => false]);
        $this->field('organic', 'boolean', ['show_in_list' => true]);
        $this->field('made', 'date', ['show_in_list' => true, 'sortable' => true]);
        $this->field('photo', 'image', ['show_in_list' => true]);
        $this->field('note', 'string', ['show_in_list' => true, 'searchable' => true, 'sortable' => true]);
        $this->field('hidden', 'string');

        $columns = array_map(static fn ($column): array => $column->toArray(), $this->extension()->columns(Product::class));

        $this->assertSame(['weight', 'organic', 'made', 'photo', 'note'], array_column($columns, 'name'));
        $this->assertSame(['text', 'boolean', 'date', 'image', 'text'], array_column($columns, 'type'));
        $this->assertSame(['Weight', 'Organic', 'Made', 'Photo', 'Note'], array_column($columns, 'label'));
        // Sorting needs the index: "note" is sortable but not filterable... nor indexed.
        $this->assertSame([true, false, true, false, true], array_column($columns, 'sortable'));
        $this->assertSame([false, false, false, false, true], array_column($columns, 'searchable'));
    }

    public function test_translatable_names(): void
    {
        $this->field('motto', 'string', ['translatable' => true]);
        $this->field('weight', 'decimal');
        $this->field('about', 'text', ['translatable' => true]);

        $this->assertSame(['motto', 'about'], $this->extension()->translatable(Product::class));
        $this->assertSame([], $this->extension()->translatable(Brand::class));
    }

    public function test_saving_takes_the_values_out_of_the_data(): void
    {
        $this->field('sizes', 'multiselect', ['options' => ['en' => "1 = One\n2 = Two"]]);
        $this->field('note', 'string');
        $this->field('weight', 'decimal');
        $product = new Product(['name' => 'Apple']);

        $data = $this->extension()->fill($product, ['name' => 'Apple', 'sizes' => [2, '3', 1], 'note' => 'Fresh', 'custom' => '{"x":1}']);

        $this->assertSame(['name' => 'Apple'], $data);

        // A field missing from the data is skipped, not the ones after it;
        // the rest of the data stays as it came.
        $plum = new Product(['name' => 'Plum']);
        $this->assertSame(['name' => 'Plum', 'active' => true], $this->extension()->fill($plum, ['name' => 'Plum', 'note' => 'Juicy', 'active' => true]));
        $this->assertSame(['note' => 'Juicy'], $this->fields()->stored($plum));
        $this->assertSame(['sizes' => ['2', '1'], 'note' => 'Fresh'], $this->fields()->stored($product), 'Only the options offered, as text; nothing for the missing weight.');

        // A single value of a list of several is kept for the type to read.
        $this->extension()->fill($product, ['sizes' => '2']);
        $this->assertSame(['2'], $this->fields()->get($product, 'sizes'));
    }

    public function test_a_definition_saves_as_it_came(): void
    {
        $field = $this->field('note', 'string');
        $data = ['code' => 'note', 'note' => 'x', 'custom' => 'y'];

        $this->assertSame($data, $this->extension()->fill($field, $data));
        $this->assertSame($data, $this->extension()->present($field, $data));
    }

    public function test_a_record_without_fields_is_shown_as_it_is(): void
    {
        $brand = new Brand(['name' => 'Acme']);

        $this->assertSame(['name' => 'Acme', 'custom' => null], $this->extension()->present($brand, ['name' => 'Acme', 'custom' => null]));
    }

    public function test_the_form_and_the_list_show_the_values_their_way(): void
    {
        $site = Site::query()->firstOrFail();
        $this->field('color', 'select', ['options' => ['en' => "red = Red\nblue = Blue"]]);
        $this->field('sizes', 'multiselect', ['options' => ['en' => "s = Small\nm = Medium"]]);
        $this->field('motto', 'string', ['translatable' => true]);
        $this->field('stock', 'number', ['per_site' => true]);
        // Promoted into a column of the table.
        $this->field('weight', 'decimal')->forceFill(['column' => 'price'])->save();
        $product = new Product(['name' => 'Apple']);
        $product->setAttribute('price', 12);
        $product->setAttribute('custom', json_encode([
            'color' => 'red',
            'sizes' => ['m', 'xl'],
            'motto' => ['en' => 'Crisp', 'de' => 'Knackig'],
            'stock' => [$site->id => 5, $site->id + 1 => 9],
        ]));

        $form = $this->extension()->present($product, ['name' => 'Apple', 'custom' => 'raw']);
        $this->assertEquals(['name' => 'Apple', 'color' => 'red', 'sizes' => ['m', 'xl'], 'motto' => ['en' => 'Crisp', 'de' => 'Knackig'], 'stock' => 5, 'weight' => 12], $form);

        $list = $this->extension()->present($product, ['name' => 'Apple'], 'list');
        $this->assertEquals(['name' => 'Apple', 'color' => 'Red', 'sizes' => 'Medium, xl', 'motto' => 'Crisp', 'stock' => 5, 'weight' => 12], $list);
    }

    public function test_a_list_shows_an_option_by_its_text_whatever_its_stored_type(): void
    {
        $this->field('grade', 'select', ['options' => ['en' => "1.5 = One and a half\n1 = One"]]);
        $this->field('sizes', 'multiselect', ['options' => ['en' => 's = Small']]);
        $product = new Product(['name' => 'Apple']);
        $product->setAttribute('custom', json_encode(['grade' => 1.5, 'sizes' => [9, 's']]));

        $this->assertSame(['grade' => 'One and a half', 'sizes' => '9, Small'], $this->extension()->present($product, [], 'list'));
    }

    public function test_odd_stored_values_show_as_empty_in_the_form(): void
    {
        $this->field('motto', 'string', ['translatable' => true]);
        $this->field('stock', 'number', ['per_site' => true]);
        $this->field('note', 'string');
        $product = new Product(['name' => 'Apple']);
        $product->setAttribute('custom', json_encode(['motto' => 'plain', 'stock' => 5]));

        $this->assertSame(['motto' => null, 'stock' => null, 'note' => null], $this->extension()->present($product, []));
    }

    public function test_the_fields_button_is_for_those_who_may_open_the_screen(): void
    {
        $this->field('weight', 'decimal');
        $this->field('color', 'string');

        $this->assertSame([], $this->extension()->links(Product::class), 'Nobody signed in.');

        $editor = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $editor->roles()->attach(AdminRole::query()->create(['name' => 'Products', 'permissions' => ['catalog_products.view']]));
        $this->actingAs($editor, 'admin');
        $this->assertSame([], $this->extension()->links(Product::class), 'May not open the fields screen.');

        $this->actingAs($this->admin(), 'admin');
        $this->assertSame([[
            'name' => 'custom-fields',
            'label' => __('atrium-custom-fields::custom-fields.button', ['count' => 2]),
            'href' => route('admin.custom_fields.index', ['module' => (new Product)->getMorphClass()]),
        ]], $this->extension()->links(Product::class));
        $this->assertStringContainsString('2', $this->extension()->links(Product::class)[0]['label']);

        // Not an admin module.
        $this->assertSame([], $this->extension()->links(User::class));
    }

    private function extension(): CustomFieldsExtension
    {
        $this->fields()->flush();

        return $this->app->make(CustomFieldsExtension::class);
    }
}
