<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Concerns;

use Atrium\CustomFields\CustomFields;

/**
 * Optional sugar for a module's model. Custom fields work without it; with
 * it the JSON column is cast to an array and the values read nicely:
 *
 *     use HasCustomFields;
 *
 *     $product->custom('color');               // in the current site and language
 *     $product->setCustom('color', 'red')->save();
 */
trait HasCustomFields
{
    protected function initializeHasCustomFields(): void
    {
        $this->mergeCasts([app(CustomFields::class)->column() => 'array']);
    }

    public function custom(string $code, ?string $locale = null, ?int $site = null): mixed
    {
        return app(CustomFields::class)->get($this, $code, $locale, $site);
    }

    public function setCustom(string $code, mixed $value, ?string $locale = null, ?int $site = null): static
    {
        app(CustomFields::class)->set($this, $code, $value, $locale, $site);

        return $this;
    }
}
