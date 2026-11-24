<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Fixtures;

use Atrium\Catalog\Models\Product;

/**
 * A module whose form does not start with the `params` tab.
 */
final class Booklet extends Product
{
    /**
     * @return array<int,array<string,string>>
     */
    public static function getAdminTabs(): array
    {
        return [['name' => 'cover', 'label' => 'Cover'], ['name' => 'back', 'label' => 'Back']];
    }
}
