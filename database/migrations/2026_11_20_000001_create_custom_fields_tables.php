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
 * The definitions of custom fields, and the typed index of the values that
 * are filtered or sorted by. The values themselves live in a JSON column of
 * each module's own table (`custom`), added when its first field is created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table): void {
            $table->id();
            // The model's morph class: its class name or its morph-map alias.
            $table->string('module', 191);
            $table->string('code', 64);
            $table->string('type', 16);
            $table->json('label');
            $table->json('hint')->nullable();
            // Per language, one "value = Label" a line.
            $table->json('options')->nullable();
            $table->string('link_module', 191)->nullable();
            $table->string('tab', 64)->default('params');
            $table->integer('sort')->default(500);
            // Null: every site. Otherwise the field exists on that site only.
            $table->foreignId('site_id')->nullable()->index();
            $table->boolean('required')->default(false);
            $table->boolean('per_site')->default(false);
            $table->boolean('translatable')->default(false);
            $table->boolean('filterable')->default(false);
            $table->boolean('sortable')->default(false);
            $table->boolean('searchable')->default(false);
            $table->boolean('show_in_list')->default(false);
            // A real column of the module's table after atrium:custom-fields:promote.
            $table->string('column', 64)->nullable();
            $table->timestamps();

            $table->unique(['module', 'code']);
        });

        Schema::create('custom_field_index', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 191);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('field_id');
            // 0: the same value on every site; '': the same in every language.
            $table->unsignedBigInteger('site_id')->default(0);
            $table->string('locale', 12)->default('');
            $table->decimal('value_num', 20, 6)->nullable();
            $table->string('value_str', 191)->nullable();
            $table->date('value_date')->nullable();

            // The field and its site/language first: every lookup names them,
            // then the value (equality, range, order, GROUP BY), then the record.
            $table->index(['field_id', 'site_id', 'locale', 'value_str', 'entity_id'], 'cfi_str');
            $table->index(['field_id', 'site_id', 'locale', 'value_num', 'entity_id'], 'cfi_num');
            $table->index(['field_id', 'site_id', 'locale', 'value_date', 'entity_id'], 'cfi_date');
            // A record first: the sort's join and the filters' "id in (…)"
            // look up one record's row of a field, as do sync and delete.
            $table->index(['entity_id', 'field_id', 'site_id', 'locale', 'value_num', 'value_str', 'value_date'], 'cfi_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_index');
        Schema::dropIfExists('custom_fields');
    }
};
