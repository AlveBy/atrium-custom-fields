<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\CustomFields\Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * Custom fields in the module API and the MCP tools, like the module's own.
 */
final class ApiTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    public function test_the_api_writes_filters_and_sorts_by_custom_fields(): void
    {
        $this->field('color', 'select', ['options' => ['en' => "red\nblue"], 'filterable' => true]);
        $this->field('weight', 'decimal', ['filterable' => true, 'sortable' => true]);
        $this->field('subtitle', 'string', ['searchable' => true, 'show_in_list' => true]);
        $token = $this->token(['catalog_products.view', 'catalog_products.create'], ['catalog_products:read', 'catalog_products:write']);

        $this->withToken($token)->postJson('/api/v1/catalog_products', ['name' => 'Apple', 'active' => true, 'color' => 'red', 'weight' => 1.5, 'subtitle' => 'Crunchy'])
            ->assertCreated()
            ->assertJsonPath('data.color', 'red')
            ->assertJsonPath('data.weight', 1.5)
            ->assertJsonMissingPath('data.custom');
        $this->product('Plum', ['color' => 'blue', 'weight' => 0.5, 'subtitle' => 'Juicy']);
        $this->product('Cherry', ['color' => 'red', 'weight' => 3]);

        $names = fn (string $query): array => array_column($this->withToken($token)->getJson('/api/v1/catalog_products?'.$query)->assertOk()->json('data'), 'name');

        $this->assertSame(['Apple', 'Cherry'], $names('filter[color]=red&sort=weight'));
        $this->assertSame(['Cherry', 'Apple', 'Plum'], $names('sort=weight&direction=desc'));
        $this->assertSame(['Apple', 'Plum'], $names('filter[weight][min]=0&filter[weight][max]=2&sort=weight&direction=desc'));
        $this->assertSame(['Plum', 'Apple'], $names('filter[color]=blue,red&filter[weight][max]=2&sort=name&direction=desc'));
        $this->assertSame(['Plum'], $names('search=juic'));
    }

    public function test_the_docs_and_the_mcp_schema_list_the_fields(): void
    {
        $this->field('color', 'select', ['options' => ['en' => "red = Red\nblue = Blue"], 'filterable' => true]);

        $module = $this->app->make(ApiDocumentation::class)->modules(['catalog_products'])['catalog_products'];
        $color = collect($module['fields'])->firstWhere('name', 'color');
        $this->assertNotNull($color);

        $token = $this->token(['catalog_products.view'], ['catalog_products:read']);
        $result = $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
            'name' => 'describe_module', 'arguments' => ['module' => 'catalog_products'],
        ]])->assertOk()->json('result');

        $this->assertFalse($result['isError'] ?? true, json_encode($result));
        $result = $result['content'][0]['text'];

        $this->assertContains('color', array_column(json_decode((string) $result, true)['fields'], 'name'));
    }

    /**
     * @param  array<int,string>  $permissions
     * @param  array<int,string>  $abilities
     */
    private function token(array $permissions, array $abilities): string
    {
        $user = AdminUser::query()->create([
            'name' => 'Editor',
            'email' => uniqid('editor').'@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
        $user->roles()->attach(AdminRole::query()->create(['name' => uniqid('Role'), 'permissions' => $permissions]));

        return $user->createToken('assistant', $abilities)->plainTextToken;
    }
}
