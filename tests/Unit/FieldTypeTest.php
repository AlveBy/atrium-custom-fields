<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Unit;

use Atrium\CustomFields\FieldType;
use Atrium\CustomFields\Tests\TestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What each type allows, and what it keeps of a value.
 */
final class FieldTypeTest extends TestCase
{
    /**
     * @return array<string,array{FieldType,string,?string,bool,bool,bool,bool,bool}>
     */
    public static function types(): array
    {
        // type, label, index column, multiple, options, translatable, searchable, sortable
        return [
            'line' => [FieldType::String, 'Line', 'value_str', false, false, true, true, true],
            'text' => [FieldType::Text, 'Text', null, false, false, true, true, false],
            'integer' => [FieldType::Number, 'Integer', 'value_num', false, false, false, false, true],
            'decimal' => [FieldType::Decimal, 'Decimal', 'value_num', false, false, false, false, true],
            'yes / no' => [FieldType::Boolean, 'Yes / no', 'value_num', false, false, false, false, true],
            'date' => [FieldType::Date, 'Date', 'value_date', false, false, false, false, true],
            'list' => [FieldType::Select, 'List', 'value_str', false, true, false, false, true],
            'multiple choice' => [FieldType::MultiSelect, 'Multiple choice', 'value_str', true, true, false, false, false],
            'link' => [FieldType::Link, 'Link to a record', 'value_num', false, false, false, false, true],
            'file' => [FieldType::File, 'File', null, false, false, false, false, false],
            'image' => [FieldType::Image, 'Image', null, false, false, false, false, false],
        ];
    }

    #[DataProvider('types')]
    public function test_what_a_type_allows(FieldType $type, string $label, ?string $column, bool $multiple, bool $options, bool $translatable, bool $searchable, bool $sortable): void
    {
        $this->assertSame($label, $type->label());
        $this->assertSame($column, $type->indexColumn());
        $this->assertSame($multiple, $type->isMultiple());
        $this->assertSame($options, $type->hasOptions());
        $this->assertSame($translatable, $type->canBeTranslatable());
        $this->assertSame($searchable, $type->canBeSearchable());
        $this->assertSame($sortable, $type->canBeSortable());
    }

    /**
     * @return array<string,array{FieldType,mixed,mixed}>
     */
    public static function values(): array
    {
        return [
            'empty text is no value' => [FieldType::String, '', null],
            'null is no value' => [FieldType::Number, null, null],
            'an empty list is no value' => [FieldType::MultiSelect, [], null],
            'an empty list is no number' => [FieldType::Number, [], null],
            'an empty list is no decimal' => [FieldType::Decimal, [], null],
            'an empty list is neither yes nor no' => [FieldType::Boolean, [], null],
            'an empty list is no link' => [FieldType::Link, [], null],
            'an empty list is no date' => [FieldType::Date, [], null],
            'an empty list is no line' => [FieldType::String, [], null],
            'a zero is a value' => [FieldType::Number, '0', 0],
            'false is a value' => [FieldType::Boolean, false, false],
            'a line' => [FieldType::String, 'Red', 'Red'],
            'a number as a line' => [FieldType::String, 12, '12'],
            'a list is not a line' => [FieldType::Text, ['a'], null],
            'an integer from text' => [FieldType::Number, '42', 42],
            'an integer from a decimal' => [FieldType::Number, 4.7, 4],
            'not an integer' => [FieldType::Number, 'many', null],
            'a decimal with a point' => [FieldType::Decimal, '1.5', 1.5],
            'a decimal with a comma' => [FieldType::Decimal, '1,5', 1.5],
            'a negative decimal with a comma' => [FieldType::Decimal, '-2,25', -2.25],
            'thousands with spaces' => [FieldType::Decimal, " 1\u{00A0}234\u{202F}567 ", 1234567.0],
            'a comma with thousands stays a comma' => [FieldType::Decimal, '1,500.5', null],
            'two commas are not a number' => [FieldType::Decimal, '1,5,0', null],
            'a decimal from an integer' => [FieldType::Decimal, 3, 3.0],
            'not a decimal' => [FieldType::Decimal, 'heavy', null],
            'a space between thousands and a comma' => [FieldType::Decimal, '1 234,5', 1234.5],
            'tabs and line breaks around' => [FieldType::Decimal, "\t1,5\n", 1.5],
            'a plus sign' => [FieldType::Decimal, '+1,5', 1.5],
            'yes' => [FieldType::Boolean, 'yes', true],
            'one' => [FieldType::Boolean, '1', true],
            'off' => [FieldType::Boolean, 'off', false],
            'neither yes nor no' => [FieldType::Boolean, 'maybe', null],
            'a link' => [FieldType::Link, '7', 7],
            'not a link' => [FieldType::Link, 'seven', null],
            'a date' => [FieldType::Date, '2026-11-17', '2026-11-17'],
            'a date and time' => [FieldType::Date, '2026-11-17 23:15:00', '2026-11-17'],
            'a date object' => [FieldType::Date, new DateTimeImmutable('2026-02-03 04:05:06'), '2026-02-03'],
            'not a date' => [FieldType::Date, 'someday', null],
            'a number is not a date' => [FieldType::Date, 20261117, null],
            'a list' => [FieldType::MultiSelect, ['s', 'm'], ['s', 'm']],
            'one value of a list' => [FieldType::MultiSelect, 'm', ['m']],
            'a list without repeats, blanks and odd items' => [FieldType::MultiSelect, ['s', '', 's', ['x'], 2, null], ['s', '2']],
            'a list of nothing' => [FieldType::MultiSelect, ['', null], null],
            'an option' => [FieldType::Select, 'red', 'red'],
            'a file' => [FieldType::File, 'docs/a.pdf', 'docs/a.pdf'],
            'an image' => [FieldType::Image, 'img/a.png', 'img/a.png'],
        ];
    }

    #[DataProvider('values')]
    public function test_what_a_type_keeps_of_a_value(FieldType $type, mixed $value, mixed $expected): void
    {
        $this->assertSame($expected, $type->normalize($value));
    }
}
