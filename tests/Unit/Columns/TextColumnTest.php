<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Columns;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TextColumnTest extends TestCase
{
    public function test_its_settings_reach_the_list(): void
    {
        $array = TextColumn::make('page_url')
            ->limit(40)
            ->default('—')
            ->copyable()
            ->url('/admin/pages')
            ->urlFrom('page_url')
            ->toArray();

        $this->assertSame(40, $array['limit']);
        $this->assertSame('—', $array['default']);
        $this->assertTrue($array['copyable']);
        $this->assertSame('/admin/pages', $array['url']);
        $this->assertSame('page_url', $array['urlFrom']);
    }

    public function test_copying_can_be_switched_off_again(): void
    {
        $this->assertFalse(TextColumn::make('a')->copyable()->copyable(false)->toArray()['copyable']);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function values(): array
    {
        return [
            'plain' => ['Garden chair', 'Garden chair'],
            'null' => [null, '-'],
            'empty string' => ['', '-'],
            'zero as a string is a value' => ['0', '0'],
            'only spaces are a value' => ['   ', '   '],
            'Cyrillic' => ['Садовый стул', 'Садовый стул'],
            'Chinese' => ['花园椅', '花园椅'],
            'Japanese' => ['ガーデンチェア', 'ガーデンチェア'],
            'Arabic, right to left' => ['كرسي حديقة', 'كرسي حديقة'],
            'Hebrew, right to left' => ['כיסא גן', 'כיסא גן'],
            'emoji' => ['Chair 🪑', 'Chair 🪑'],
            'HTML is left to the template to escape' => ['<script>alert(1)</script>', '<script>alert(1)</script>'],
            'a number without a limit' => [42, '42'],
        ];
    }

    #[DataProvider('values')]
    public function test_a_value_without_a_limit_is_shown_whole_and_an_empty_one_as_the_default(mixed $value, string $expected): void
    {
        $this->assertSame($expected, (string) TextColumn::make('name')->formatValue($value, null));
    }

    public function test_an_empty_value_shows_the_chosen_default(): void
    {
        $column = TextColumn::make('name')->default('not set');

        $this->assertSame('not set', $column->formatValue(null, null));
        $this->assertSame('not set', $column->formatValue('', null));
        $this->assertSame('set', $column->formatValue('set', null));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function limits(): array
    {
        return [
            'shorter than the limit' => ['abcd', 5, 'abcd'],
            'exactly the limit' => ['abcde', 5, 'abcde'],
            'one over the limit' => ['abcdef', 5, 'abcde...'],
            'much longer' => [str_repeat('a', 300), 3, 'aaa...'],
            'Cyrillic counted by characters, not bytes' => ['Привет', 6, 'Привет'],
            'Cyrillic cut by characters' => ['Привет, мир', 6, 'Привет...'],
            'Chinese cut by characters' => ['花园椅子很好', 2, '花园...'],
            'emoji cut by characters' => ['🪑🪑🪑', 2, '🪑🪑...'],
            'a limit of one' => ['ab', 1, 'a...'],
        ];
    }

    #[DataProvider('limits')]
    public function test_a_long_value_is_cut_at_the_limit_by_characters(string $value, int $limit, string $expected): void
    {
        $this->assertSame($expected, TextColumn::make('name')->limit($limit)->formatValue($value, null));
    }

    public function test_a_limit_of_zero_means_no_limit(): void
    {
        $this->assertSame('abcdef', TextColumn::make('name')->limit(0)->formatValue('abcdef', null));
    }
}
