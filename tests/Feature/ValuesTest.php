<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\Index\Indexer;
use Atrium\CustomFields\Tests\Fixtures\ProductWithFields;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Values in the JSON column: by language, by site, typed.
 */
final class ValuesTest extends TestCase
{
    public function test_values_are_stored_typed_in_one_json_column(): void
    {
        $this->field('weight', 'decimal');
        $this->field('pieces', 'number');
        $this->field('organic', 'boolean');
        $this->field('released', 'date');
        $this->field('sizes', 'multiselect', ['options' => ['en' => "s\nm"]]);
        $this->field('note', 'string');

        $product = $this->product('Apple', ['weight' => '1.5', 'pieces' => '12', 'organic' => '1', 'released' => '2026-01-10 10:00', 'sizes' => ['s', 's', 'm'], 'note' => '']);
        $product = Product::query()->findOrFail($product->id);

        $this->assertEquals(['weight' => 1.5, 'pieces' => 12, 'organic' => true, 'released' => '2026-01-10', 'sizes' => ['s', 'm']], $this->fields()->stored($product));
        $this->assertSame(1.5, $this->fields()->get($product, 'weight'));
        $this->assertSame(12, $this->fields()->get($product, 'pieces'));
        $this->assertTrue($this->fields()->get($product, 'organic'));
        $this->assertNull($this->fields()->get($product, 'note'));

        // An empty value removes the key.
        $this->fields()->set($product, 'sizes', []);
        $this->assertArrayNotHasKey('sizes', $this->fields()->stored($product));
    }

    public function test_a_translatable_field_falls_back_to_the_default_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $this->app->make(ContentLanguages::class)->flush();
        $this->field('subtitle', 'string', ['translatable' => true, 'filterable' => true]);

        $product = $this->product('Apple', ['subtitle' => ['en' => 'Sweet', 'de' => 'Süß']]);
        $plum = $this->product('Plum', ['subtitle' => ['en' => 'Sour']]);

        $this->assertSame('Süß', $this->fields()->get($product, 'subtitle', 'de'));
        $this->assertSame('Sour', $this->fields()->get($plum, 'subtitle', 'de'));

        // One language at a time keeps the others.
        $this->fields()->set($plum, 'subtitle', 'Sauer', 'de');
        $this->assertSame(['en' => 'Sour', 'de' => 'Sauer'], $this->fields()->stored($plum)['subtitle']);

        // The index has a row per language, the missing ones filled from the default.
        $rows = DB::table(Indexer::TABLE)->where('entity_id', $product->id)->orderBy('locale')->pluck('value_str', 'locale')->all();
        $this->assertSame(['de' => 'Süß', 'en' => 'Sweet'], $rows);

        $this->app->setLocale('de');
        $this->assertSame(['Plum'], Product::query()->whereCustom('subtitle', 'Sour')->pluck('name')->all());
        $this->assertSame(['Apple'], Product::query()->whereCustom('subtitle', 'Süß')->pluck('name')->all());
        $this->app->setLocale('en');
        $this->assertSame([], Product::query()->whereCustom('subtitle', 'Süß')->pluck('name')->all());
    }

    public function test_a_per_site_field_has_a_value_for_every_site(): void
    {
        $sites = $this->app->make(Sites::class);
        $main = $sites->default();
        $second = Site::query()->create(['code' => 'second', 'name' => 'Second', 'domains' => ['second.test']]);
        $sites->flush();
        $this->field('stock', 'number', ['per_site' => true, 'filterable' => true, 'sortable' => true]);

        $product = new Product(['name' => 'Apple', 'active' => true]);
        $this->fields()->set($product, 'stock', 5, site: $main->id);
        $this->fields()->set($product, 'stock', 0, site: $second->id);
        $product->save();

        $this->assertSame(5, $this->fields()->get($product, 'stock'));
        $this->assertSame(0, $this->fields()->get($product, 'stock', site: $second->id));
        $this->assertSame(['Apple'], Product::query()->whereCustom('stock', '>', 0)->pluck('name')->all());

        $sites->setCurrent($second);
        $this->assertSame(0, $this->fields()->get($product, 'stock'));
        $this->assertSame(0, Product::query()->withoutGlobalScopes()->whereCustom('stock', '>', 0)->count());
    }

    public function test_a_field_of_one_site_is_only_there(): void
    {
        $sites = $this->app->make(Sites::class);
        $second = Site::query()->create(['code' => 'second', 'name' => 'Second', 'domains' => ['second.test']]);
        $sites->flush();
        $this->field('promo', 'string', ['site_id' => $second->id]);

        $this->assertNull($this->fields()->field(Product::class, 'promo'));
        $this->assertCount(1, $this->fields()->all(Product::class));

        $sites->setCurrent($second);
        $this->assertNotNull($this->fields()->field(Product::class, 'promo'));
    }

    public function test_the_trait_reads_and_writes_values(): void
    {
        $this->field('color', 'string');

        $product = new ProductWithFields(['name' => 'Apple', 'active' => true]);
        $product->setCustom('color', 'red')->save();

        $fresh = ProductWithFields::query()->findOrFail($product->id);
        $this->assertSame('red', $fresh->custom('color'));
        $this->assertSame(['color' => 'red'], $fresh->getAttribute('custom'));
    }

    public function test_the_definitions_are_read_once_until_flushed(): void
    {
        $this->field('color', 'string');
        $this->assertCount(1, $this->fields()->all(Product::class));

        // Behind the cache's back: no saved event flushes it.
        DB::table('custom_fields')->insert(['module' => (new Product)->getMorphClass(), 'code' => 'size', 'type' => 'string', 'label' => '{"en":"Size"}']);
        DB::enableQueryLog();
        $this->assertCount(1, $this->fields()->all(Product::class));
        $this->assertTrue($this->fields()->isInstalled());
        $this->assertSame([], DB::getQueryLog(), 'neither the fields nor the table are looked up again');
        DB::disableQueryLog();

        $this->fields()->flush();
        $this->assertCount(2, $this->fields()->all(Product::class));
    }

    public function test_the_fields_of_the_site_are_a_list(): void
    {
        $second = Site::query()->create(['code' => 'second', 'name' => 'Second', 'domains' => ['second.test']]);
        $this->app->make(Sites::class)->flush();
        $this->field('promo', 'string', ['site_id' => $second->id, 'sort' => 1]);
        $color = $this->field('color', 'string', ['sort' => 2]);

        $this->assertSame([0], array_keys($this->fields()->fields(Product::class)));
        $this->assertSame($color->id, $this->fields()->fields(Product::class)[0]->id);
        $this->assertSame([], $this->fields()->fields(Site::class), 'a module without fields');
    }

    public function test_an_unknown_field_names_the_module(): void
    {
        try {
            $this->fields()->fieldOrFail(Product::class, 'nope');
            $this->fail('No exception for a class.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('No custom field [nope] in ['.Product::class.'].', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No custom field [nope] in ['.ProductWithFields::class.'].');

        $this->fields()->fieldOrFail(new ProductWithFields, 'nope');
    }

    public function test_a_promoted_field_is_written_to_its_column_only(): void
    {
        $this->field('cost', 'decimal')->forceFill(['column' => 'price'])->save();
        $product = new Product(['name' => 'Apple', 'active' => true]);

        $this->fields()->set($product, 'cost', '2,5');

        $this->assertSame('2.50', $product->getAttribute('price'), 'normalized, then cast by the model');
        $this->assertSame([], $this->fields()->stored($product));
        $this->assertNull($product->getAttribute('custom'));
    }

    public function test_a_translatable_field_of_every_site_keeps_each_site_s_languages(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $this->app->make(ContentLanguages::class)->flush();
        $sites = $this->app->make(Sites::class);
        $main = $sites->default()->id;
        $second = Site::query()->create(['code' => 'second', 'name' => 'Second', 'domains' => ['second.test']])->id;
        $sites->flush();
        $this->field('motto', 'string', ['translatable' => true, 'per_site' => true]);
        $product = new Product(['name' => 'Apple', 'active' => true]);

        $this->fields()->set($product, 'motto', 'Crisp', 'en', $main);
        $this->fields()->set($product, 'motto', 'Fresh', 'en', $second);
        $this->fields()->set($product, 'motto', 'Knackig', 'de', $main);
        $this->fields()->set($product, 'motto', 'Frisch', 'de', $second);
        $this->fields()->set($product, 'motto', 'Neu', 'de');

        $this->assertSame([$main => ['en' => 'Crisp', 'de' => 'Neu'], $second => ['en' => 'Fresh', 'de' => 'Frisch']], $this->fields()->stored($product)['motto']);

        // An empty translation goes, and so does a site left without any.
        $this->fields()->set($product, 'motto', ['en' => '', 'de' => ''], site: $second);
        $this->assertSame([$main => ['en' => 'Crisp', 'de' => 'Neu']], $this->fields()->stored($product)['motto']);
        $this->fields()->set($product, 'motto', ['en' => null, 'de' => ''], site: $main);
        $this->assertSame([], $this->fields()->stored($product));
    }

    public function test_a_translatable_field_starts_over_on_what_is_no_list_of_languages(): void
    {
        $this->field('motto', 'string', ['translatable' => true]);
        $this->field('slogan', 'string', ['translatable' => true, 'per_site' => true]);
        $site = $this->fields()->site();
        $product = new Product(['name' => 'Apple', 'active' => true, 'custom' => json_encode(['motto' => 'plain', 'slogan' => [$site => 'plain']])]);

        // Each translation is normalized: a number becomes text, an empty one goes.
        $this->fields()->set($product, 'motto', ['en' => 7, 'de' => '']);
        $this->fields()->set($product, 'slogan', 'Fresh', 'en');

        $this->assertSame(['motto' => ['en' => '7'], 'slogan' => [$site => ['en' => 'Fresh']]], $this->fields()->stored($product));
    }

    public function test_the_default_language_without_languages_is_the_fallback_locale(): void
    {
        config(['app.fallback_locale' => 'pl']);
        $this->assertSame('en', $this->fields()->defaultLocale(), 'the first language of the site');

        // No languages and no app locale either: the content languages have
        // one without a code.
        Language::query()->delete();
        config(['app.locale' => '']);
        $this->app->make(ContentLanguages::class)->flush();
        $this->assertSame('pl', $this->fields()->defaultLocale());
    }
}
