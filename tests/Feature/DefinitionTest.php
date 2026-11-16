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
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Validation\ValidationException;
use Spatie\Translatable\Translatable;

/**
 * A field's definition: the flags its type allows, its texts, its options
 * and the checks of the "Fields" screen.
 */
final class DefinitionTest extends TestCase
{
    public function test_flags_the_type_cannot_have_are_dropped_on_save(): void
    {
        $about = $this->field('about', 'text', ['filterable' => true, 'sortable' => true, 'link_module' => Brand::class, 'tab' => '']);
        $this->assertFalse($about->filterable, 'a long text has no index');
        $this->assertFalse($about->sortable);
        $this->assertNull($about->link_module, 'only a link links');
        $this->assertSame('params', $about->tab);

        $size = $this->field('size', 'number', ['translatable' => true, 'searchable' => true]);
        $this->assertFalse($size->translatable);
        $this->assertFalse($size->searchable);

        $note = $this->field('note', 'string', ['searchable' => true, 'per_site' => true]);
        $this->assertFalse($note->searchable, 'a value per site is searched nowhere');
        $this->assertTrue($note->per_site);

        $motto = $this->field('motto', 'string');
        $this->assertFalse($motto->searchable, 'not ticked, not searched');
        $this->assertSame('detail', $this->field('maker', 'link', ['link_module' => Brand::class, 'tab' => 'detail'])->tab);
        $this->assertSame(Brand::class, CustomField::query()->where('code', 'maker')->value('link_module'));

        $sku = $this->field('sku', 'string', ['translatable' => true, 'per_site' => true]);
        $this->assertTrue($sku->translatable);
        $sku->forceFill(['column' => 'article'])->save();
        $this->assertFalse($sku->translatable, 'a column holds one value');
        $this->assertFalse($sku->per_site);
    }

    public function test_a_field_without_a_label_is_named_by_its_code(): void
    {
        $field = new CustomField(['code' => 'weight', 'type' => 'decimal', 'hint' => ['en' => '']]);

        $this->assertSame('weight', $field->labelText());
        $this->assertNull($field->hintText());

        $field->setTranslation('label', 'en', '')->setTranslation('hint', 'en', 'In kilograms');
        $this->assertSame('weight', $field->labelText());
        $this->assertSame('In kilograms', $field->hintText());
    }

    public function test_options_are_read_line_by_line(): void
    {
        $field = new CustomField(['type' => 'select', 'options' => ['en' => "s = Small\n\n   \n  m  \nxl = Extra = large\n= nothing\nl =  "]]);

        $this->assertSame(['s' => 'Small', 'm' => 'm', 'xl' => 'Extra = large', 'l' => 'l'], $field->optionList('en'));
    }

    public function test_another_language_names_the_options_by_value_or_by_line(): void
    {
        $this->languages('de', 'uk');
        $field = new CustomField(['type' => 'select', 'options' => [
            'en' => "red = Red\nblue = Blue\ngreen = Green",
            // By line: the first two lines.
            'de' => "Rot\nBlau",
            // By value, in another order: the value wins over the line.
            'uk' => "blue = Синій\nred = Червоний",
        ]]);

        $this->assertSame(['red' => 'Rot', 'blue' => 'Blau', 'green' => 'Green'], $field->optionList('de'));
        $this->assertSame(['red' => 'Червоний', 'blue' => 'Синій', 'green' => 'Green'], $field->optionList('uk'));
    }

    public function test_the_values_come_from_the_default_language_only(): void
    {
        // Russian is the default language; the app's own fallback is English.
        Language::query()->update(['is_default' => false]);
        Language::query()->create(['code' => 'ru', 'name' => 'Русский', 'sort' => 50, 'is_default' => true]);
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $this->app->make(ContentLanguages::class)->flush();
        config(['app.fallback_locale' => 'en']);

        $russian = new CustomField(['type' => 'select', 'options' => ['ru' => 'a = А', 'en' => 'a = A']]);
        $this->assertSame(['a' => 'А'], $russian->optionList('de'), 'German has no labels: the Russian ones');

        $english = new CustomField(['type' => 'select', 'options' => ['en' => 'a = A']]);
        $this->assertSame([], $english->optionList('ru'));

        // Not even when the app takes any language for a missing one.
        $this->app->instance(Translatable::class, (new Translatable)->fallback(fallbackAny: true));
        $this->assertSame([], (new CustomField(['type' => 'select', 'options' => ['en' => 'a = A']]))->optionList('ru'));
    }

    public function test_the_options_are_parsed_once_until_they_change(): void
    {
        $field = new CustomField(['type' => 'select', 'options' => ['en' => 'a = A']]);
        $this->assertSame(['a' => 'A'], $field->optionList('en'));

        // Behind the model's back: still the parsed ones.
        $field->setRawAttributes(['type' => 'select', 'options' => json_encode(['en' => 'b = B'])]);
        $this->assertSame(['a' => 'A'], $field->optionList('en'));

        $field->setAttribute('code', 'grade');
        $this->assertSame(['a' => 'A'], $field->optionList('en'), 'another attribute keeps them');

        $field->setAttribute('options', ['en' => 'c = C']);
        $this->assertSame(['c' => 'C'], $field->optionList('en'));
    }

    public function test_the_option_values_are_text(): void
    {
        $field = new CustomField(['type' => 'select', 'options' => ['en' => "1 = One\n2 = Two\nx"]]);

        $this->assertSame(['1', '2', 'x'], $field->optionValues());
    }

    public function test_the_screen_s_form_and_list(): void
    {
        $fields = [];

        foreach (CustomField::getAdministrationFields() as $field) {
            $fields[$field->getName()] = $field->toArray();
        }

        $this->assertSame('Module', $fields['module']['label']);
        $this->assertSame('Options', $fields['options']['label']);
        $this->assertSame(6, $fields['options']['rows']);
        $this->assertSame(500, $fields['sort']['default']);

        $columns = array_map(static fn ($column): array => $column->toArray(), CustomField::getAdministrationListColumns());
        $this->assertSame(['label', 'code', 'type_label', 'module_label', 'filterable', 'sortable', 'sort'], array_column($columns, 'name'));
        $this->assertSame(['Name', 'Code', 'Type'], array_slice(array_column($columns, 'label'), 0, 3));
    }

    public function test_the_list_names_the_type_and_the_module(): void
    {
        $field = $this->field('weight', 'decimal');

        $this->assertSame(__('atrium-custom-fields::custom-fields.types.decimal'), $field->type_label);
        $this->assertSame('Products', $field->module_label);
        $this->assertSame('', (new CustomField)->type_label, 'a field being created has no type yet');
    }

    public function test_module_code_and_type_are_fixed_once_the_field_exists(): void
    {
        $field = $this->field('weight', 'decimal');
        $module = (new Product)->getMorphClass();

        $this->assertSame(['module' => $module, 'code' => 'weight', 'type' => 'decimal'], $field->transformAdminData(['module' => $module, 'code' => 'weight', 'type' => 'decimal']));
        $this->assertErrors(['module'], $field, ['module' => (new Brand)->getMorphClass()]);
        $this->assertErrors(['code'], $field, ['code' => 'mass']);
        // Checked as the type it was submitted with: only the type is wrong.
        $this->assertErrors(['type'], $field, ['type' => 'number', 'sortable' => true]);
        $this->assertErrors(['type'], $this->field('about', 'text'), ['type' => 'number', 'sortable' => true]);
    }

    public function test_the_flags_of_an_existing_field_are_checked_against_its_type(): void
    {
        $about = $this->field('about', 'text');

        $this->assertSame([], $about->transformAdminData([]), 'nothing ticked: nothing to say');
        $this->assertErrors(['sortable'], $about, ['sortable' => true]);
    }

    public function test_a_list_needs_options_in_some_language(): void
    {
        $field = new CustomField;

        $this->assertErrors(['options'], $field, ['type' => 'select', 'options' => ['en' => '', 'de' => ['nested']]]);
        $this->assertErrors(['options'], $field, ['type' => 'select', 'options' => " \n "]);
        $this->assertSame(['type' => 'select', 'options' => ['en' => '', 'de' => "a\nb"]], $field->transformAdminData(['type' => 'select', 'options' => ['en' => '', 'de' => "a\nb"]]));
        $this->assertSame(['type' => 'select', 'options' => 'a'], $field->transformAdminData(['type' => 'select', 'options' => 'a']));
        $this->assertSame(['type' => 'select', 'options' => 7], $field->transformAdminData(['type' => 'select', 'options' => 7]));
    }

    private function languages(string ...$codes): void
    {
        foreach ($codes as $i => $code) {
            Language::query()->create(['code' => $code, 'name' => strtoupper($code), 'sort' => 200 + $i]);
        }

        $this->app->make(ContentLanguages::class)->flush();
    }

    /**
     * @param  array<int,string>  $keys
     * @param  array<string,mixed>  $data
     */
    private function assertErrors(array $keys, CustomField $field, array $data): void
    {
        try {
            $field->transformAdminData($data);
            $this->fail('No errors.');
        } catch (ValidationException $e) {
            $this->assertSame($keys, array_keys($e->errors()));
        }
    }
}
