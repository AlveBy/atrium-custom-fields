<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields;

/**
 * What a custom field holds, and so how it is edited, validated, stored in
 * the JSON column and indexed.
 */
enum FieldType: string
{
    case String = 'string';
    case Text = 'text';
    case Number = 'number';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Link = 'link';
    case File = 'file';
    case Image = 'image';

    public function label(): string
    {
        return __('atrium-custom-fields::custom-fields.types.'.$this->value);
    }

    /**
     * The index column the value goes to, or null when the type cannot be
     * filtered or sorted by (long text, files).
     */
    public function indexColumn(): ?string
    {
        return match ($this) {
            self::String, self::Select, self::MultiSelect => 'value_str',
            self::Number, self::Decimal, self::Boolean, self::Link => 'value_num',
            self::Date => 'value_date',
            self::Text, self::File, self::Image => null,
        };
    }

    public function isMultiple(): bool
    {
        return $this === self::MultiSelect;
    }

    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::MultiSelect;
    }

    /**
     * Only text can be written per language: an option, a number or a
     * date is the same in every one (option labels are translated instead).
     */
    public function canBeTranslatable(): bool
    {
        return $this === self::String || $this === self::Text;
    }

    public function canBeSearchable(): bool
    {
        return $this === self::String || $this === self::Text;
    }

    public function canBeSortable(): bool
    {
        return $this->indexColumn() !== null && ! $this->isMultiple();
    }

    /**
     * Turn whatever came from the form, the API or code into what is kept in
     * the JSON: null for "no value".
     */
    public function normalize(mixed $value): mixed
    {
        // An empty list is no value either: every type below turns it into null.
        if ($value === '' || $value === null) {
            return null;
        }

        return match ($this) {
            self::Number => is_numeric($value) ? (int) $value : null,
            self::Decimal => $this->decimal($value),
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            self::Link => is_numeric($value) ? (int) $value : null,
            self::Date => $this->date($value),
            self::MultiSelect => $this->list($value),
            default => is_scalar($value) ? (string) $value : null,
        };
    }

    /**
     * A number with either separator: "1.5" or "1,5", as a German or Russian
     * filter form sends it. A space or a narrow space between thousands is
     * dropped; "1,500" stays one and a half, never fifteen hundred.
     */
    private function decimal(mixed $value): ?float
    {
        if (is_string($value)) {
            // A comma is the decimal separator: one that is not ("1,500.5",
            // "1,5,0") leaves two points, and that is no number. Tabs and line
            // breaks around a number are fine as they are.
            $value = str_replace(',', '.', str_replace([' ', "\u{00A0}", "\u{202F}"], '', $value));
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) && ! $value instanceof \DateTimeInterface) {
            return null;
        }

        $time = $value instanceof \DateTimeInterface ? $value->getTimestamp() : strtotime($value);

        return $time === false ? null : date('Y-m-d', $time);
    }

    /**
     * @return array<int,string>|null
     */
    private function list(mixed $value): ?array
    {
        $items = array_values(array_unique(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '', (array) $value),
            static fn (string $item): bool => $item !== '',
        )));

        return $items === [] ? null : $items;
    }
}
