<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Atrium\Brands\Models\Brand;
use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\CustomFacets;
use Atrium\CustomFields\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * The facets come back typed and in the same order every time, whatever
 * the database returns.
 */
final class FacetsTest extends TestCase
{
    public function test_a_database_that_returns_text_still_gives_typed_facets(): void
    {
        $this->field('battery', 'number', ['filterable' => true]);
        $this->field('brand', 'link', ['link_module' => Brand::class, 'filterable' => true]);
        $this->field('weight', 'decimal', ['filterable' => true]);
        $this->field('waterproof', 'boolean', ['filterable' => true]);
        $this->product('Watch', ['battery' => 40, 'brand' => 3, 'weight' => 0.25, 'waterproof' => true]);
        $this->product('Laptop', ['battery' => 12, 'brand' => 5, 'weight' => 1.5, 'waterproof' => false]);

        // As MySQL does with emulated prepares: every value is text.
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);

        try {
            $this->assertSame([
                'battery' => ['min' => 12, 'max' => 40],
                'brand' => ['min' => 3, 'max' => 5],
                'weight' => ['min' => 0.25, 'max' => 1.5],
                'waterproof' => ['min' => false, 'max' => true],
            ], CustomFacets::for(Product::class)->ranges(['battery', 'brand', 'weight', 'waterproof']));

            $this->assertSame(
                ['battery' => [12 => 1, 40 => 1], 'waterproof' => [0 => 1, 1 => 1]],
                CustomFacets::for(Product::class)->counts(['battery', 'waterproof']),
            );
        } finally {
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        }
    }

    public function test_the_most_frequent_value_comes_first_and_equal_counts_by_value(): void
    {
        $this->field('size', 'string', ['filterable' => true]);

        // The database groups text as text ("10" before "9"); PHP keys
        // numbers as numbers, and those are in order.
        foreach (['10', '9', 'xl', 'xl'] as $i => $size) {
            $this->product('Shirt '.$i, ['size' => $size]);
        }

        $this->assertSame(['xl' => 2, 9 => 1, 10 => 1], CustomFacets::for(Product::class)->counts(['size'])['size']);
    }
}
