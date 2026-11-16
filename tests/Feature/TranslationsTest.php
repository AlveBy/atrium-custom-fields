<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Feature;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

/**
 * Every admin language has every line English has, and no other, with the
 * same placeholders.
 */
final class TranslationsTest extends TestCase
{
    private const LANGUAGES = ['be', 'de', 'en', 'es', 'fr', 'it', 'ja', 'kk', 'pl', 'pt_BR', 'ru', 'tr', 'uk', 'uz', 'zh_CN'];

    public function test_every_language_has_the_english_keys(): void
    {
        $dir = dirname(__DIR__, 2).'/lang';
        $english = Arr::dot(require $dir.'/en/custom-fields.php');
        $languages = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));

        $this->assertSame(self::LANGUAGES, $languages);

        foreach ($languages as $language) {
            $lines = Arr::dot(require $dir.'/'.$language.'/custom-fields.php');

            $this->assertSame(array_keys($english), array_keys($lines), $language);
            $this->assertNotContains('', $lines, $language);

            foreach ($english as $key => $line) {
                preg_match_all('/:[a-z_]+/', (string) $line, $expected);
                preg_match_all('/:[a-z_]+/', (string) $lines[$key], $actual);
                $this->assertEqualsCanonicalizing($expected[0], $actual[0], "{$language}: {$key}");
            }
        }
    }
}
