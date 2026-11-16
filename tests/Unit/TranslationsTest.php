<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Translation\MessageSelector;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

/**
 * Every interface language must be complete and well-formed against English.
 */
final class TranslationsTest extends TestCase
{
    private const LANG = __DIR__.'/../../lang';

    /** Laravel picks the form by these rules; a string must have that many. */
    private const PLURAL_FORMS = [
        'en' => 2, 'de' => 2, 'es' => 2, 'it' => 2, 'pt_BR' => 2, 'fr' => 2,
        'ru' => 3, 'uk' => 3, 'be' => 3, 'pl' => 3,
        'ja' => 1, 'zh_CN' => 1, 'tr' => 1, 'kk' => 1, 'uz' => 1,
    ];

    /**
     * Data providers run before Laravel boots, so the list is read from the
     * package config file directly.
     *
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        $config = require __DIR__.'/../../config/atrium.php';
        $codes = array_keys($config['locales']);

        return array_combine($codes, array_map(static fn (string $code): array => [$code], $codes));
    }

    #[DataProvider('locales')]
    public function test_the_language_has_every_key_of_english_and_nothing_else(string $locale): void
    {
        $english = $this->strings('en');
        $own = $this->strings($locale);

        $this->assertSame([], array_values(array_diff(array_keys($english), array_keys($own))), "Missing in {$locale}");
        $this->assertSame([], array_values(array_diff(array_keys($own), array_keys($english))), "Unknown in {$locale}");
    }

    #[DataProvider('locales')]
    public function test_placeholders_and_plural_forms_match_english(string $locale): void
    {
        $english = $this->strings('en');

        foreach ($this->strings($locale) as $key => $line) {
            $this->assertNotSame('', trim($line), "{$locale}: {$key} is empty");

            if (! isset($english[$key])) {
                continue;
            }

            $this->assertSame($this->placeholders($english[$key]), $this->placeholders($line), "{$locale}: {$key} placeholders differ from English");

            if (str_contains($english[$key], '|')) {
                $this->assertCount(self::PLURAL_FORMS[$locale], explode('|', $line), "{$locale}: {$key} needs ".self::PLURAL_FORMS[$locale].' plural form(s)');
            } else {
                $this->assertStringNotContainsString('|', $line, "{$locale}: {$key} is not a plural string");
            }
        }
    }

    public function test_every_listed_language_has_a_plural_rule_here(): void
    {
        $this->assertSame([], array_values(array_diff(array_keys(self::locales()), array_keys(self::PLURAL_FORMS))));
    }

    public function test_the_vue_plural_rules_match_laravel(): void
    {
        $script = realpath(__DIR__.'/../../resources/js/Support/plural.js');
        $numbers = [...range(0, 130), 1001, 1011, 1021, 1112, 1114, 1122];
        $codes = array_keys(self::PLURAL_FORMS);

        $process = new Process(['node', '--input-type=module', '-e', sprintf(
            'import { pluralIndex } from %s; const out = {}; for (const c of %s) out[c] = %s.map((n) => pluralIndex(c, n)); console.log(JSON.stringify(out));',
            json_encode('file://'.$script),
            json_encode($codes),
            json_encode($numbers),
        )]);
        $process->run();

        if (! $process->isSuccessful() && str_contains($process->getErrorOutput().$process->getOutput(), 'not found')) {
            $this->markTestSkipped('node is not installed');
        }

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $vue = json_decode($process->getOutput(), true);

        $selector = new MessageSelector;

        foreach ($codes as $code) {
            $laravel = array_map(static fn (int $n): int => $selector->getPluralIndex($code, $n), $numbers);
            $this->assertSame($laravel, $vue[$code], "Plural rule for {$code} differs from Laravel");
        }
    }

    /**
     * @return array<string, string>
     */
    private function strings(string $locale): array
    {
        $strings = [];

        foreach (glob(self::LANG."/{$locale}/*.php") ?: [] as $file) {
            $strings[basename($file, '.php')] = require $file;
        }

        return Arr::dot($strings);
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $line): array
    {
        preg_match_all('/:([a-z_]+)/i', $line, $matches);
        $found = array_values(array_unique($matches[1]));
        sort($found);

        return $found;
    }
}
