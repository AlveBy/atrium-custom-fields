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
 * Tabs of a module's form that an administrator adds for custom fields.
 * A field names its tab by the code, in `custom_fields.tab`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_tabs', function (Blueprint $table): void {
            $table->id();
            // The model's morph class, as in custom_fields.module.
            $table->string('module', 191);
            $table->string('code', 64);
            $table->json('label');
            $table->integer('sort')->default(500);
            // Null: every site. Otherwise the tab exists on that site only.
            $table->foreignId('site_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['module', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_tabs');
    }
};
