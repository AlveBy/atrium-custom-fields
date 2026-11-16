<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | The JSON column of the values
    |--------------------------------------------------------------------------
    |
    | Every module with custom fields keeps their values in this column of
    | its own table. It is added to the table when the module gets its first
    | field; a module may also have it in its own migration:
    |
    |     $table->json('custom')->nullable();
    |
    */

    'column' => env('ATRIUM_CUSTOM_FIELDS_COLUMN', 'custom'),
];
