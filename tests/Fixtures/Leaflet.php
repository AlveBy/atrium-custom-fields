<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Fixtures;

use Atrium\Admin\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;

/**
 * A model whose form names its tabs with Tab objects, one without a name.
 */
final class Leaflet extends Model
{
    /**
     * @return array<int,Tab|array<string,string>>
     */
    public static function getAdminTabs(): array
    {
        return [
            Tab::make('cover')->label('Cover page'),
            Tab::make('inside'),
            Tab::make(''),
            ['label' => 'No name'],
            ['name' => 'back'],
            ['name' => 'params', 'label' => 'Parameters'],
        ];
    }
}
