<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Database;

use Atrium\Admin\Modules\DemoSeeder;
use Atrium\Admin\Modules\Soft;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\Models\CustomField;

/**
 * Five filterable fields on the demo shop's products: weight, battery
 * life, waterproof, connectivity and warranty, named in every interface
 * language. The weight is in pounds when the shop's main language is
 * English (a US shop), in kilograms everywhere else.
 */
final class CustomFieldsDemoSeeder extends DemoSeeder
{
    /** Pounds in a kilogram. */
    private const LB = 2.20462;

    /**
     * kg, hours, waterproof, connectivity, warranty years; by product code.
     *
     * @var array<string,array{float,int,bool,list<string>,string}>
     */
    private const PRODUCTS = [
        'aero-14' => [1.2, 18, false, ['usb_c', 'wifi', 'bluetooth'], '2'],
        'aero-16-pro' => [2.1, 12, false, ['usb_c', 'wifi', 'bluetooth'], '3'],
        'slate-11' => [0.48, 12, false, ['usb_c', 'wifi', 'bluetooth', 'cellular'], '2'],
        'quiet-one' => [0.25, 40, false, ['bluetooth', 'usb_c'], '2'],
        'pulse-mini' => [0.35, 12, true, ['bluetooth'], '1'],
        'stride' => [0.04, 168, true, ['bluetooth', 'cellular'], '2'],
    ];

    public function run(): bool
    {
        if (! Soft::available(Product::class)
            || CustomField::query()->where('module', (new Product)->getMorphClass())->exists()) {
            return false;
        }

        $pounds = self::locale() === 'en';
        $fields = $this->fields($pounds);
        $custom = app(CustomFields::class);

        $products = Product::query()->whereIn('code', array_keys(self::PRODUCTS))->get();

        foreach ($products as $product) {
            [$kg, $hours, $waterproof, $connectivity, $warranty] = self::PRODUCTS[$product->code];
            $values = [
                'weight' => $pounds ? round($kg * self::LB, 1) : $kg,
                'battery' => $hours,
                'waterproof' => $waterproof,
                'connectivity' => $connectivity,
                'warranty' => $warranty,
            ];

            foreach ($values as $code => $value) {
                $custom->write($product, $fields[$code], $value);
            }

            $product->save();
        }

        return true;
    }

    /**
     * The main language of the demo: the app's locale, or English when the
     * demo has no texts in it. The country, its units and its currency
     * follow from it.
     */
    public static function locale(): string
    {
        $locale = config()->string('app.locale');

        return is_dir(self::folder($locale)) ? $locale : 'en';
    }

    /**
     * The demo texts of a language, English for one without them.
     *
     * @return array<string,mixed>
     */
    public static function texts(string $locale): array
    {
        $file = self::folder($locale).'/fields.php';

        return require is_file($file) ? $file : self::folder('en').'/fields.php';
    }

    /**
     * @return array<string,CustomField>
     */
    private function fields(bool $pounds): array
    {
        $module = (new Product)->getMorphClass();
        $definitions = [
            'weight' => ['type' => 'decimal', 'sortable' => true, 'show_in_list' => true],
            'battery' => ['type' => 'number', 'sortable' => true],
            'waterproof' => ['type' => 'boolean'],
            'connectivity' => ['type' => 'multiselect'],
            'warranty' => ['type' => 'select'],
        ];
        $fields = [];
        $sort = 0;

        foreach ($definitions as $code => $definition) {
            $fields[$code] = CustomField::query()->create($definition + [
                'module' => $module,
                'code' => $code,
                'label' => $this->translations(fn (array $texts): string => $code === 'weight'
                    ? $texts['weight'].', '.$texts[$pounds ? 'lb' : 'kg']
                    : $texts[$code]),
                'hint' => $code === 'waterproof' ? $this->translations(fn (array $texts): string => $texts['waterproof_hint']) : null,
                'options' => isset(self::texts('en')[$code.'_options'])
                    ? $this->translations(fn (array $texts): string => self::optionLines($texts[$code.'_options']))
                    : null,
                'tab' => 'params',
                'sort' => $sort += 10,
                'filterable' => true,
            ]);
        }

        return $fields;
    }

    /**
     * A text in every interface language, keyed by its content language
     * code ("pt_BR" → "pt-br").
     *
     * @param  callable(array<string,mixed>):string  $text
     * @return array<string,string>
     */
    private function translations(callable $text): array
    {
        $translations = [];

        foreach (array_keys((array) config('atrium.locales', ['en' => 'English'])) as $locale) {
            $translations[strtolower(str_replace('_', '-', (string) $locale))] = $text(self::texts((string) $locale));
        }

        return $translations;
    }

    /**
     * @param  array<string,string>  $options
     */
    private static function optionLines(array $options): string
    {
        $lines = [];

        foreach ($options as $value => $label) {
            $lines[] = $value.' = '.$label;
        }

        return implode("\n", $lines);
    }

    private static function folder(string $locale): string
    {
        return dirname(__DIR__, 2).'/resources/demo/'.basename($locale);
    }
}
