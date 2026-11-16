<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests;

use Atrium\Admin\AtriumServiceProvider;
use Atrium\Admin\Models\AdminUser;
use Atrium\Brands\BrandsServiceProvider;
use Atrium\Catalog\CatalogServiceProvider;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\CustomFields;
use Atrium\CustomFields\CustomFieldsServiceProvider;
use Atrium\CustomFields\Models\CustomField;
use Atrium\Theme\ThemeServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Tighten\Ziggy\ZiggyServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * On MySQL and PostgreSQL the whole suite shares one database: each
     * class migrates it again with the modules it installs.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        RefreshDatabaseState::$migrated = false;
    }

    public static function tearDownAfterClass(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDownAfterClass();
    }

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            ZiggyServiceProvider::class,
            McpServiceProvider::class,
            AtriumServiceProvider::class,
            ThemeServiceProvider::class,
            CatalogServiceProvider::class,
            BrandsServiceProvider::class,
            CustomFieldsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $target = env('DB_CONNECTION');
        $config->set('database.default', in_array($target, ['mysql', 'pgsql'], true) ? $target : 'testing');
        $config->set('atrium.modules', ['alveby/atrium-catalog', 'alveby/atrium-brands', 'alveby/atrium-custom-fields']);
        $config->set('atrium.auth.two_factor.enabled', false);
        $config->set('atrium.dashboard.timings.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function admin(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'name' => 'Admin',
            'email' => uniqid('admin').'@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $admin->forceFill(['is_superadmin' => true])->save();

        return $admin;
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    protected function field(string $code, string $type, array $attributes = []): CustomField
    {
        return CustomField::query()->create([
            'module' => (new Product)->getMorphClass(),
            'code' => $code,
            'type' => $type,
            'label' => ['en' => ucfirst($code)],
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string,mixed>  $custom
     */
    protected function product(string $name, array $custom = []): Product
    {
        $product = new Product(['name' => $name, 'active' => true]);

        foreach ($custom as $code => $value) {
            $this->fields()->set($product, $code, $value);
        }

        $product->save();

        return $product;
    }

    protected function fields(): CustomFields
    {
        return $this->app->make(CustomFields::class);
    }
}
