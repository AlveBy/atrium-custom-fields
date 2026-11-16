<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Admin\Modules\Modules;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\Database\CustomFieldsDemoSeeder;
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The demo's fields speak the shop's main language and use its country's
 * units: pounds for an English (US) shop, kilograms everywhere else.
 */
final class DemoTest extends TestCase
{
    /**
     * @return array<string,array{string,string,string,string,float}>
     */
    public static function countries(): array
    {
        return [
            'German shop' => ['de', 'Gewicht, kg', 'WLAN', '2 Jahre', 1.2],
            'Japanese shop' => ['ja', '重さ, kg', 'Wi-Fi', '2年', 1.2],
            'Russian shop' => ['ru', 'Вес, кг', 'Wi-Fi', '2 года', 1.2],
            'Brazilian shop' => ['pt_BR', 'Peso, kg', 'Wi-Fi', '2 anos', 1.2],
            'US shop' => ['en', 'Weight, lb', 'Wi-Fi', '2 years', 2.6],
            'unknown language falls back to the US shop' => ['xx', 'Weight, lb', 'Wi-Fi', '2 years', 2.6],
        ];
    }

    #[DataProvider('countries')]
    public function test_the_demo_fields_follow_the_main_language(string $locale, string $weight, string $wifi, string $warranty, float $aero): void
    {
        $content = strtolower(str_replace('_', '-', $locale === 'xx' ? 'en' : $locale));
        $this->seedDemo($locale);

        $fields = app(CustomFields::class);
        $product = Product::query()->where('code', 'aero-14')->firstOrFail();

        $this->assertSame($weight, $fields->field($product, 'weight')?->labelText($content));
        $this->assertSame($wifi, $fields->field($product, 'connectivity')?->optionList($content)['wifi']);
        $this->assertSame($warranty, $fields->field($product, 'warranty')?->optionList($content)['2']);
        $this->assertEquals($aero, $fields->get($product, 'weight'));
        $this->assertSame(['usb_c', 'wifi', 'bluetooth'], $fields->get($product, 'connectivity'));
    }

    public function test_plurals_of_the_warranty(): void
    {
        $this->seedDemo('ru');
        $warranty = app(CustomFields::class)->field(Product::class, 'warranty');

        $this->assertSame(['1' => '1 год', '2' => '2 года', '3' => '3 года'], $warranty?->optionList('ru'));
        $this->assertSame(['1' => '1 рік', '2' => '2 роки', '3' => '3 роки'], $warranty?->optionList('uk'));
        $this->assertSame(['1' => '1 год', '2' => '2 гады', '3' => '3 гады'], $warranty?->optionList('be'));
        $this->assertSame(['1' => '1 rok', '2' => '2 lata', '3' => '3 lata'], $warranty?->optionList('pl'));
    }

    public function test_the_filters_and_facets_work_on_the_demo(): void
    {
        $this->seedDemo('de');

        // A German editor types the decimal comma.
        $light = Product::query()->whereCustom('weight', '<=', '1,5')->orderBy('code')->pluck('code')->all();
        $this->assertSame(['aero-14', 'pulse-mini', 'quiet-one', 'slate-11', 'stride'], $light);

        $this->assertSame(['pulse-mini', 'stride'], Product::query()->whereCustom('waterproof', true)->orderBy('code')->pluck('code')->all());
        $this->assertSame(['slate-11', 'stride'], Product::query()->whereCustom('connectivity', 'cellular')->orderBy('code')->pluck('code')->all());
    }

    public function test_it_runs_once(): void
    {
        $this->seedDemo('de');

        $this->assertFalse((new CustomFieldsDemoSeeder)->run());
        $this->assertSame(5, CustomField::query()->count());

        CustomField::query()->delete();

        $this->assertTrue((new CustomFieldsDemoSeeder)->run());
        $this->assertSame(5, CustomField::query()->count());
    }

    public function test_the_fields_come_in_order_with_what_a_shop_filters_and_sorts_by(): void
    {
        $this->seedDemo('de');
        $fields = CustomField::query()->orderBy('sort')->get()->keyBy('code');

        $this->assertSame(['weight' => 10, 'battery' => 20, 'waterproof' => 30, 'connectivity' => 40, 'warranty' => 50], $fields->map->sort->all());
        $this->assertSame(['weight', 'battery'], $fields->filter->sortable->keys()->all());
        $this->assertSame(['weight'], $fields->filter->show_in_list->keys()->all());
        $this->assertSame(['waterproof'], $fields->filter(static fn (CustomField $field): bool => $field->hint !== null)->keys()->all());
        $this->assertTrue($fields->every->filterable);
        $this->assertSame(['stride', 'aero-14'], Product::query()->whereIn('code', ['aero-14', 'stride'])->orderByCustom('battery', 'desc')->pluck('code')->all());
    }

    public function test_the_texts_of_a_language_without_them_are_english(): void
    {
        $this->assertSame(CustomFieldsDemoSeeder::texts('en'), CustomFieldsDemoSeeder::texts('xx'));
        $this->assertSame('Gewicht', CustomFieldsDemoSeeder::texts('de')['weight']);

        config(['app.locale' => 'de']);
        $this->assertSame('de', CustomFieldsDemoSeeder::locale());
        config(['app.locale' => 'xx']);
        $this->assertSame('en', CustomFieldsDemoSeeder::locale());
    }

    public function test_without_interface_languages_the_fields_are_named_in_english(): void
    {
        config(['atrium' => Arr::except((array) config('atrium'), 'locales')]);
        $this->seedDemo('en');

        $this->assertSame(['en' => 'Weight, lb'], app(CustomFields::class)->field(Product::class, 'weight')?->getTranslations('label'));
    }

    private function seedDemo(string $locale): void
    {
        Storage::fake('public');
        config(['app.locale' => $locale]);

        $modules = $this->app->make(Modules::class);

        foreach ($modules->dependencyOrder($modules->installed()) as $module) {
            $modules->seedDemo($module);
        }

        app(CustomFields::class)->flush();
    }
}
