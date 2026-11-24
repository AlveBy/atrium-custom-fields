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
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tabs\Tab;
use Atrium\Brands\Models\Brand;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\Admin\CustomFieldsExtension;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\Models\CustomField;
use Atrium\CustomFields\Models\CustomFieldTab;
use Atrium\CustomFields\Support\ModuleList;
use Atrium\CustomFields\Tests\Fixtures\Booklet;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

/**
 * The tabs an administrator adds to a module's form for custom fields.
 */
final class TabsTest extends TestCase
{
    public function test_an_administrator_adds_a_tab_and_puts_a_field_on_it(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $module = (new Product)->getMorphClass();

        // The products list links to the tabs, as it links to the fields.
        $this->get(route('admin.catalog_products.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('links.1.name', 'custom-field-tabs')
            ->where('links.1.label', 'Tabs (0)')
            ->where('links.1.href', route('admin.custom_field_tabs.index', ['module' => $module])));

        // The create form starts with the module of the list it came from.
        $this->get(route('admin.custom_field_tabs.create', ['module' => $module]))
            ->assertInertia(function (AssertableInertia $page) use ($module): void {
                $fields = collect($page->toArray()['props']['fields'])->keyBy('name');
                $this->assertSame($module, $fields['module']['default']);
            });

        $this->post(route('admin.custom_field_tabs.store'), [
            'module' => $module, 'label' => ['en' => 'Delivery', 'ru' => 'Доставка'], 'code' => 'delivery', 'sort' => 15,
        ])->assertSessionDoesntHaveErrors();

        $tab = CustomFieldTab::query()->sole();
        $this->assertSame([$module, 'delivery', 15, null], [$tab->module, $tab->code, $tab->sort, $tab->site_id]);
        $this->assertSame('Delivery', $tab->labelText());

        // The field screen offers it, and takes the field onto it.
        $this->get(route('admin.custom_fields.create', ['module' => $module]))
            ->assertInertia(function (AssertableInertia $page): void {
                $options = collect($page->toArray()['props']['fields'])->keyBy('name')['tab']['options'];
                $this->assertContains('Delivery (delivery)', collect($options)->pluck('label')->all());
            });

        $this->post(route('admin.custom_fields.store'), [
            'module' => $module, 'label' => ['en' => 'Courier'], 'code' => 'courier', 'type' => 'string', 'tab' => 'delivery', 'sort' => 5,
        ])->assertSessionDoesntHaveErrors();

        $this->get(route('admin.catalog_products.create'))->assertInertia(function (AssertableInertia $page): void {
            $props = $page->toArray()['props'];
            $this->assertSame('Delivery', array_column($props['tabs'], 'label', 'name')['delivery']);
            $this->assertSame(['courier'], array_column($props['fieldsByTab']['delivery'], 'name'));
        });

        // The list of the tabs counts them.
        $this->get(route('admin.catalog_products.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('links.1.label', 'Tabs (1)'));
    }

    public function test_the_tab_stands_where_its_number_puts_it(): void
    {
        $module = (new Product)->getMorphClass();
        $this->tab('late', ['module' => $module]);
        $this->tab('early', ['module' => $module, 'sort' => 5]);
        $this->tab('middle', ['module' => $module, 'sort' => 25]);

        foreach (['late', 'early', 'middle'] as $code) {
            $this->field($code.'_note', 'string', ['tab' => $code]);
        }

        $this->actingAs($this->admin(), 'admin');

        // The tabs of a product form have no numbers: 0, 10, 20 … by their order.
        $names = array_column($this->tabs(Product::class), 'name');

        $this->assertSame(['params', 'early', 'preview', 'detail', 'middle', 'seo', 'sections', 'late'], $names);
    }

    public function test_tabs_with_their_own_numbers_are_not_renumbered(): void
    {
        $this->tab('extra', ['module' => (new Product)->getMorphClass(), 'sort' => 15]);
        $own = [Tab::make('a')->order(0), Tab::make('b')->order(10), ['name' => 'c', 'label' => 'C', 'order' => '30']];

        $tabs = $this->extension()->tabs($own, Product::class);

        $this->assertSame([0, 10, '30', 15], array_map(static fn (Tab|array $tab): int|string => $tab instanceof Tab ? $tab->getOrder() : $tab['order'], $tabs));
        $this->assertSame('extra', $tabs[3]->getName());
    }

    public function test_tabs_that_share_a_number_are_numbered_by_their_order(): void
    {
        $this->tab('extra', ['module' => (new Product)->getMorphClass(), 'sort' => 15]);
        $own = [Tab::make('a'), ['name' => 'b', 'label' => 'B'], Tab::make('c')->order(0)];
        $mixed = [['name' => 'x'], Tab::make('y')];

        $tabs = $this->extension()->tabs($own, Product::class);

        $this->assertSame([0, 10, 20, 15], array_map(static fn (Tab|array $tab): int => $tab instanceof Tab ? $tab->getOrder() : $tab['order'], $tabs));
        // An array tab with no number is 0, as a Tab without one is.
        $this->assertSame([0, 10], array_map(static fn (Tab|array $tab): int => $tab instanceof Tab ? $tab->getOrder() : $tab['order'], array_slice($this->extension()->tabs($mixed, Product::class), 0, 2)));
        $this->assertSame(['a', 'b', 'c', 'extra'], array_map(static fn (Tab|array $tab): string => $tab instanceof Tab ? $tab->getName() : $tab['name'], $tabs));
    }

    public function test_a_module_without_added_tabs_keeps_its_own_untouched(): void
    {
        $own = [Tab::make('a'), Tab::make('b')];
        $this->tab('other', ['module' => 'elsewhere']);

        $this->assertSame($own, $this->extension()->tabs($own, Product::class));
        $this->assertSame($own, $this->extension()->tabs($own, CustomField::class));
        $this->assertSame($own, $this->extension()->tabs($own, CustomFieldTab::class));
        $this->assertSame(0, $own[1]->getOrder());
    }

    public function test_a_tab_without_fields_is_not_shown(): void
    {
        $this->tab('delivery', ['module' => (new Product)->getMorphClass()]);
        $this->field('courier', 'string', ['tab' => 'delivery']);
        $this->field('color', 'string');
        $this->actingAs($this->admin(), 'admin');

        $this->assertContains('delivery', array_column($this->tabs(Product::class), 'name'));

        CustomField::query()->where('code', 'courier')->update(['tab' => 'params']);
        $this->fields()->flush();

        $this->assertNotContains('delivery', array_column($this->tabs(Product::class), 'name'));
    }

    public function test_a_tab_is_renamed_but_keeps_its_code_and_module(): void
    {
        $module = (new Product)->getMorphClass();
        $tab = $this->tab('delivery', ['module' => $module]);
        $this->actingAs($this->admin(), 'admin');
        $put = fn (array $data) => $this->put(route('admin.custom_field_tabs.update', $tab), [
            'module' => $module, 'label' => ['en' => 'Delivery'], 'code' => 'delivery', 'sort' => 500, ...$data,
        ]);

        $this->get(route('admin.custom_field_tabs.edit', $tab))->assertInertia(fn (AssertableInertia $page) => $page->where('itemTitle', 'Delivery'));

        $put(['label' => ['en' => 'Shipping', 'ru' => 'Отправка'], 'sort' => 12])->assertSessionDoesntHaveErrors();
        $tab->refresh();
        $this->assertSame(['Shipping', 12], [$tab->labelText(), $tab->sort]);

        // Fields name the tab by its code, the module owns it: both stay.
        $put(['code' => 'shipping'])->assertSessionHasErrors(['code' => __('atrium-custom-fields::custom-fields.errors.fixed')]);
        $put(['module' => Brand::class])->assertSessionHasErrors(['module' => __('atrium-custom-fields::custom-fields.errors.fixed')]);
        $this->assertSame(['delivery', $module], [$tab->fresh()?->code, $tab->fresh()?->module]);

        $this->field('courier', 'string', ['tab' => 'delivery']);
        $this->assertSame('Shipping', array_column($this->tabs(Product::class), 'label', 'name')['delivery']);
    }

    public function test_the_label_follows_the_language_and_falls_back_to_the_code(): void
    {
        $tab = $this->tab('delivery', ['module' => (new Product)->getMorphClass(), 'label' => ['en' => 'Delivery', 'ru' => 'Доставка']]);

        $this->app->setLocale('ru');
        $this->assertSame('Доставка', $tab->labelText());
        $this->assertSame('Доставка', $tab->getAdminTitle());

        $this->app->setLocale('en');
        $tab->forceFill(['label' => ['en' => '']])->save();
        $this->assertSame('delivery', $tab->fresh()?->labelText());
    }

    public function test_the_tab_is_checked(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $module = (new Product)->getMorphClass();
        $post = fn (array $data) => $this->post(route('admin.custom_field_tabs.store'), [
            'module' => $module, 'label' => ['en' => 'Tab'], 'sort' => 500, ...$data,
        ]);

        $post(['code' => 'Bad code'])->assertSessionHasErrors('code');
        $post(['code' => '9tabs'])->assertSessionHasErrors('code');
        $post(['code' => str_repeat('a', 65)])->assertSessionHasErrors('code');
        $post(['code' => ''])->assertSessionHasErrors('code');
        $post(['code' => 'nolabel', 'label' => ['en' => '']])->assertSessionHasErrors('label.en');
        $post(['module' => 'nope', 'code' => 'ok'])->assertSessionHasErrors('module');
        $post(['module' => '', 'code' => 'ok'])->assertSessionHasErrors('module');
        $post(['code' => 'ok', 'site_id' => 999])->assertSessionHasErrors('site_id');

        // The tabs of the form itself, and the default one, are taken.
        $taken = __('atrium-custom-fields::custom-fields.tabs.errors.code_taken');
        $post(['code' => 'seo'])->assertSessionHasErrors(['code' => $taken]);
        $post(['code' => 'params'])->assertSessionHasErrors(['code' => $taken]);

        // So are the tabs other modules add.
        Hook::filter('atrium.module.tabs', static fn (array $tabs, string $model): array => $model === Product::class ? [...$tabs, Tab::make('shop'), ['name' => 'blog']] : $tabs);
        $post(['code' => 'shop'])->assertSessionHasErrors(['code' => $taken]);
        $post(['code' => 'blog'])->assertSessionHasErrors(['code' => $taken]);
        $this->assertSame(0, CustomFieldTab::query()->count());

        $post(['code' => 'delivery'])->assertSessionDoesntHaveErrors();
        $post(['code' => 'delivery'])->assertSessionHasErrors(['code' => $taken]);
        // Another module may have a tab of the same code.
        $post(['module' => Brand::class, 'code' => 'delivery'])->assertSessionDoesntHaveErrors();
        $this->assertSame(2, CustomFieldTab::query()->count());
    }

    public function test_the_screen_s_form_and_list(): void
    {
        $fields = [];

        foreach (CustomFieldTab::getAdministrationFields() as $field) {
            $fields[$field->getName()] = $field->toArray();
        }

        $this->assertSame(['module', 'label', 'code', 'sort', 'site_id'], array_keys($fields));
        $this->assertSame(['Module', 'Name', 'Code', 'Sort', 'Site'], array_column($fields, 'label'));
        $this->assertSame(500, $fields['sort']['default']);
        $this->assertSame(__('atrium-custom-fields::custom-fields.tabs.hints.sort'), $fields['sort']['hint']);
        $this->assertSame(__('atrium-custom-fields::custom-fields.tabs.hints.code'), $fields['code']['hint']);
        $this->assertSame(__('atrium-custom-fields::custom-fields.tabs.hints.site'), $fields['site_id']['hint']);
        $this->assertSame('All sites', $fields['site_id']['placeholder']);

        $columns = array_map(static fn ($column): array => $column->toArray(), CustomFieldTab::getAdministrationListColumns());
        $this->assertSame(['label', 'code', 'module_label', 'sort'], array_column($columns, 'name'));
        $this->assertSame(['Name', 'Code', 'Module', 'Sort'], array_column($columns, 'label'));
    }

    public function test_the_number_is_a_number_and_the_list_names_the_module(): void
    {
        $tab = $this->tab('delivery', ['module' => (new Product)->getMorphClass(), 'sort' => '15']);

        $this->assertSame(15, $tab->sort);
        $this->assertSame('Products', $tab->module_label);
    }

    public function test_the_tabs_are_read_again_after_a_tab_is_saved_or_deleted(): void
    {
        $module = (new Product)->getMorphClass();
        $this->assertSame([], $this->fields()->allTabs(Product::class));

        $tab = $this->tab('delivery', ['module' => $module]);
        $this->assertSame(['delivery'], array_column($this->fields()->allTabs(Product::class), 'code'), 'saved: no flush by hand');

        $tab->update(['label' => ['en' => 'Shipping']]);
        $this->assertSame('Shipping', $this->fields()->allTabs(Product::class)[0]->labelText(), 'updated: the same');

        $tab->delete();
        $this->assertSame([], $this->fields()->allTabs(Product::class), 'deleted: no flush by hand');
    }

    public function test_the_default_tab_is_taken_in_a_module_without_tabs(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.custom_field_tabs.store'), [
            'module' => (new Brand)->getMorphClass(), 'label' => ['en' => 'Main'], 'code' => 'params', 'sort' => 500,
        ])->assertSessionHasErrors(['code' => __('atrium-custom-fields::custom-fields.tabs.errors.code_taken')]);
    }

    public function test_a_code_of_a_tab_of_another_site_is_taken_too(): void
    {
        $second = Site::query()->create(['code' => 'second', 'name' => 'Second', 'domains' => ['second.test']]);
        $this->app->make(Sites::class)->flush();
        $module = (new Product)->getMorphClass();
        $this->tab('promo', ['module' => $module, 'site_id' => $second->id]);
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.custom_field_tabs.store'), [
            'module' => $module, 'label' => ['en' => 'Promo'], 'code' => 'promo', 'sort' => 500,
        ])->assertSessionHasErrors(['code' => __('atrium-custom-fields::custom-fields.tabs.errors.code_taken')]);
        $this->assertSame(1, CustomFieldTab::query()->count());
    }

    public function test_a_tab_another_module_adds_with_a_number_for_a_name_does_not_break_the_check(): void
    {
        Hook::filter('atrium.module.tabs', static fn (array $tabs, string $model): array => $model === Product::class ? [...$tabs, ['name' => 7]] : $tabs);
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.custom_field_tabs.store'), [
            'module' => (new Product)->getMorphClass(), 'label' => ['en' => 'Ok'], 'code' => 'ok', 'sort' => 500,
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame(1, CustomFieldTab::query()->count());
    }

    public function test_deleting_a_tab_sends_its_fields_to_the_default_tab(): void
    {
        $module = (new Product)->getMorphClass();
        $tab = $this->tab('delivery', ['module' => $module]);
        $this->tab('delivery', ['module' => Brand::class]);
        $courier = $this->field('courier', 'string', ['tab' => 'delivery']);
        $other = $this->field('color', 'string', ['tab' => 'detail']);
        $brandField = CustomField::query()->create(['module' => Brand::class, 'code' => 'note', 'type' => 'string', 'label' => ['en' => 'Note'], 'tab' => 'delivery']);
        $product = $this->product('Apple', ['courier' => 'DHL']);
        $this->actingAs($this->admin(), 'admin');

        $this->delete(route('admin.custom_field_tabs.destroy', $tab))->assertSessionDoesntHaveErrors();

        $this->assertNull(CustomFieldTab::query()->where('module', $module)->first());
        $this->assertSame('params', $courier->fresh()?->tab);
        $this->assertSame('detail', $other->fresh()?->tab, 'a field of another tab stays');
        $this->assertSame('delivery', $brandField->fresh()?->tab, 'a tab of another module of the same code stays');
        $this->assertSame('DHL', $this->fields()->get($product->fresh() ?? $product, 'courier'), 'the value is kept');

        // The form has no such tab, the field is on the first one.
        $this->get(route('admin.catalog_products.edit', $product))->assertInertia(function (AssertableInertia $page): void {
            $props = $page->toArray()['props'];
            $this->assertNotContains('delivery', array_column($props['tabs'], 'name'));
            $this->assertContains('courier', array_column($props['fieldsByTab']['params'], 'name'));
        });
    }

    public function test_a_field_of_a_tab_that_is_gone_is_on_the_first_tab(): void
    {
        // Behind the model's back: the tab is gone but the field still names it.
        $this->field('courier', 'string', ['tab' => 'vanished']);
        $this->field('color', 'string', ['tab' => 'detail']);

        $tabs = collect($this->extension()->fields(Product::class))->mapWithKeys(fn ($field): array => [$field->getName() => $field->getTab()]);

        $this->assertSame(['courier' => 'params', 'color' => 'detail'], $tabs->all());
    }

    public function test_a_field_of_a_tab_that_is_gone_goes_to_the_first_tab_of_the_form(): void
    {
        CustomField::query()->create(['module' => Booklet::class, 'code' => 'note', 'type' => 'string', 'label' => ['en' => 'Note'], 'tab' => 'vanished']);
        CustomField::query()->create(['module' => Booklet::class, 'code' => 'back', 'type' => 'string', 'label' => ['en' => 'Back'], 'tab' => 'back']);

        $tabs = collect($this->extension()->fields(Booklet::class))->mapWithKeys(fn ($field): array => [$field->getName() => $field->getTab()]);

        $this->assertSame(['note' => 'cover', 'back' => 'back'], $tabs->all());
    }

    public function test_the_tabs_are_read_once_until_flushed(): void
    {
        $this->tab('delivery', ['module' => (new Product)->getMorphClass()]);
        $this->assertCount(1, $this->fields()->allTabs(Product::class));

        // Behind the cache's back: no saved event flushes it.
        DB::table('custom_field_tabs')->insert(['module' => (new Product)->getMorphClass(), 'code' => 'more', 'label' => '{"en":"More"}']);
        DB::enableQueryLog();
        $this->assertCount(1, $this->fields()->allTabs(Product::class));
        $this->assertSame([], DB::getQueryLog(), 'neither the tabs nor the table are looked up again');
        DB::disableQueryLog();

        $this->fields()->flush();
        $this->assertCount(2, $this->fields()->allTabs(Product::class));
    }

    public function test_a_module_without_tabs_gets_them_with_its_first_added_tab(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->field('note', 'string', ['tab' => 'params'])->forceFill(['module' => Brand::class])->save();

        // No tab, no tabs: the form is flat.
        $this->get(route('admin.brands.create'))->assertInertia(fn (AssertableInertia $page) => $page->where('useTabs', false)->missing('tabs'));

        $this->tab('notes', ['module' => Brand::class, 'label' => ['en' => 'Notes'], 'sort' => 10]);
        CustomField::query()->create(['module' => Brand::class, 'code' => 'memo', 'type' => 'text', 'label' => ['en' => 'Memo'], 'tab' => 'notes']);

        $this->get(route('admin.brands.create'))->assertInertia(function (AssertableInertia $page): void {
            $props = $page->toArray()['props'];

            $this->assertTrue($props['useTabs']);
            $this->assertSame(['params', 'notes'], array_column($props['tabs'], 'name'));
            $this->assertSame('General', $props['tabs'][0]['label']);
            $this->assertSame(['memo'], array_column($props['fieldsByTab']['notes'], 'name'));
            // The module's own fields and the field of the default tab stay on the first.
            $this->assertContains('name', array_column($props['fieldsByTab']['params'], 'name'));
            $this->assertContains('note', array_column($props['fieldsByTab']['params'], 'name'));
        });

        // It saves as any other form does.
        $this->post(route('admin.brands.store'), ['name' => 'Acme', 'code' => 'acme', 'sort' => 500, 'active' => true, 'memo' => 'Hello', 'note' => 'N'])->assertSessionDoesntHaveErrors();
        $brand = Brand::query()->where('code', 'acme')->firstOrFail();
        $this->assertSame('Hello', $this->fields()->get($brand, 'memo'));
    }

    public function test_a_tab_of_one_site_is_only_there(): void
    {
        $sites = $this->app->make(Sites::class);
        $second = Site::query()->create(['code' => 'second', 'name' => 'Second', 'domains' => ['second.test']]);
        $sites->flush();
        $module = (new Product)->getMorphClass();
        $this->tab('promo', ['module' => $module, 'site_id' => $second->id]);
        $this->tab('delivery', ['module' => $module]);
        $this->field('banner', 'string', ['tab' => 'promo']);
        $this->field('courier', 'string', ['tab' => 'delivery']);

        $this->assertSame(['delivery'], array_column($this->fields()->tabs(Product::class), 'code'));
        $this->assertSame([0], array_keys($this->fields()->tabs(Product::class)), 'a list');
        $this->assertSame(['promo', 'delivery'], array_column($this->fields()->allTabs(Product::class), 'code'));
        $this->assertSame(['banner' => 'params', 'courier' => 'delivery'], $this->fieldTabs());

        $sites->setCurrent($second);
        $this->fields()->flush();

        $this->assertSame(['promo', 'delivery'], array_column($this->fields()->tabs(Product::class), 'code'));
        $this->assertSame(['banner' => 'promo', 'courier' => 'delivery'], $this->fieldTabs());
    }

    public function test_only_those_with_the_right_may_manage_the_tabs(): void
    {
        $module = (new Product)->getMorphClass();
        $tab = $this->tab('delivery', ['module' => $module]);
        $viewer = $this->editor(['catalog_products.view', 'custom_field_tabs.view']);
        $nobody = $this->editor(['catalog_products.view'], 'nobody@example.com');
        $fieldsOnly = $this->editor(['catalog_products.view', 'custom_fields.view'], 'fields@example.com');
        $data = ['module' => $module, 'label' => ['en' => 'New'], 'code' => 'fresh', 'sort' => 500];

        // Without the right the button is not there, and the screens are shut.
        $this->actingAs($nobody, 'admin');
        $this->get(route('admin.catalog_products.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('links', []));
        $this->get(route('admin.custom_field_tabs.index'))->assertForbidden();
        $this->get(route('admin.custom_field_tabs.create'))->assertForbidden();
        $this->post(route('admin.custom_field_tabs.store'), $data)->assertForbidden();
        $this->put(route('admin.custom_field_tabs.update', $tab), $data)->assertForbidden();
        $this->delete(route('admin.custom_field_tabs.destroy', $tab))->assertForbidden();

        // The right to the fields is not the right to the tabs.
        $this->actingAs($fieldsOnly, 'admin');
        $this->get(route('admin.catalog_products.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('links.0.name', 'custom-fields')->missing('links.1'));
        $this->get(route('admin.custom_field_tabs.index'))->assertForbidden();

        // Viewing is not changing.
        $this->actingAs($viewer, 'admin');
        $this->get(route('admin.catalog_products.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('links.0.name', 'custom-field-tabs')->missing('links.1'));
        $this->get(route('admin.custom_field_tabs.index'))->assertOk();
        $this->post(route('admin.custom_field_tabs.store'), $data)->assertForbidden();
        $this->put(route('admin.custom_field_tabs.update', $tab), $data)->assertForbidden();
        $this->delete(route('admin.custom_field_tabs.destroy', $tab))->assertForbidden();
        $this->assertSame(['delivery'], CustomFieldTab::query()->pluck('code')->all());
        $this->assertSame('Delivery', $tab->fresh()?->labelText());
    }

    public function test_the_tab_screen_is_not_a_module_for_fields(): void
    {
        $modules = $this->app->make(ModuleList::class);

        $this->assertArrayNotHasKey(CustomFieldTab::class, $modules->models());
        $this->assertArrayNotHasKey(CustomField::class, $modules->models());
        $this->assertSame([], $this->extension()->fields(CustomFieldTab::class));
        $this->assertSame([], $this->extension()->columns(CustomFieldTab::class));
        $this->assertSame([], $this->extension()->translatable(CustomFieldTab::class));
        $this->assertSame([], $this->extension()->links(CustomFieldTab::class));

        $tab = $this->tab('delivery', ['module' => (new Product)->getMorphClass()]);
        $data = ['label' => 'x'];
        $this->assertSame($data, $this->extension()->fill($tab, $data));
        $this->assertSame($data, $this->extension()->present($tab, $data));
    }

    public function test_the_tab_select_names_the_added_tabs(): void
    {
        $this->tab('delivery', ['module' => (new Product)->getMorphClass(), 'label' => ['en' => 'Delivery']]);
        // The form's own tab of the same name keeps its own label.
        $this->tab('preview', ['module' => Brand::class, 'label' => ['en' => 'Mine']]);

        $options = $this->app->make(ModuleList::class)->tabOptions();

        $this->assertSame('Delivery (delivery)', $options['delivery']);
        $this->assertSame('Preview (preview)', $options['preview']);
    }

    public function test_nothing_breaks_before_the_tabs_table_is_there(): void
    {
        Schema::drop('custom_field_tabs');
        $this->app->forgetInstance(CustomFields::class);

        $fields = $this->app->make(CustomFields::class);
        $this->assertSame([], $fields->allTabs(Product::class));
        $this->assertSame([], $fields->tabs(Product::class));
        $this->assertSame(['x'], $this->app->make(CustomFieldsExtension::class)->tabs(['x'], Product::class));
    }

    /**
     * @return array<int,array<string,mixed>> the tabs of the products form, as the page gets them
     */
    private function tabs(string $model): array
    {
        $this->fields()->flush();

        return $model::getFieldsGroupedByTabs()['tabs'];
    }

    /**
     * @return array<string,string> field => the tab the form puts it on
     */
    private function fieldTabs(): array
    {
        return collect($this->extension()->fields(Product::class))->mapWithKeys(fn ($field): array => [$field->getName() => $field->getTab()])->all();
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function tab(string $code, array $attributes): CustomFieldTab
    {
        return CustomFieldTab::query()->create([
            'label' => ['en' => ucfirst($code)],
            ...$attributes,
            'code' => $code,
        ]);
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions, string $email = 'editor@example.com'): AdminUser
    {
        $editor = AdminUser::query()->create(['name' => 'Editor', 'email' => $email, 'password' => 'secret-password', 'is_active' => true]);
        $editor->roles()->attach(AdminRole::query()->create(['name' => 'Role '.$email, 'permissions' => $permissions]));

        return $editor;
    }

    private function extension(): CustomFieldsExtension
    {
        $this->fields()->flush();

        return $this->app->make(CustomFieldsExtension::class);
    }
}
