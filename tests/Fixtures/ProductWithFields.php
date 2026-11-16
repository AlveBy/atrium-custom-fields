<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Fixtures;

use Atrium\Catalog\Models\Product;
use Atrium\CustomFields\Concerns\HasCustomFields;

/**
 * A product with the trait, stored as a product: what an app does when it
 * adds HasCustomFields to its own model.
 */
class ProductWithFields extends Product
{
    use HasCustomFields;

    public function getMorphClass(): string
    {
        return Product::class;
    }
}
