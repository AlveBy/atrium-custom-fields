<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The column the first field would add. Added here because DDL in a test
 * would end the test's transaction on MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_products', static function (Blueprint $table): void {
            $table->json('custom')->nullable();
        });
    }
};
