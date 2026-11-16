<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model that has tabs but switched them off: one plain form.
 */
final class Flyer extends Model
{
    /**
     * @return array<int,array<string,string>>
     */
    public static function getAdminTabs(): array
    {
        return [['name' => 'params', 'label' => 'Parameters']];
    }

    public static function usesTabs(): bool
    {
        return false;
    }
}
