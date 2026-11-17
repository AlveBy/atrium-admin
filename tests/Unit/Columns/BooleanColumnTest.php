<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Columns;

use Atrium\Admin\Columns\BooleanColumn;
use Atrium\Admin\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class BooleanColumnTest extends TestCase
{
    public function test_by_default_it_is_a_green_and_red_icon(): void
    {
        $array = BooleanColumn::make('active')->toArray();

        $this->assertSame('green', $array['trueColor']);
        $this->assertSame('red', $array['falseColor']);
        $this->assertTrue($array['asIcon']);
    }

    public function test_without_labels_it_says_yes_and_no_in_every_language(): void
    {
        foreach (array_keys(config('atrium.locales')) as $locale) {
            app()->setLocale($locale);

            $array = BooleanColumn::make('active')->toArray();

            $this->assertSame(__('atrium::common.yes'), $array['trueLabel'], $locale);
            $this->assertSame(__('atrium::common.no'), $array['falseLabel'], $locale);
            $this->assertNotSame('atrium::common.yes', $array['trueLabel'], $locale);
            $this->assertNotSame('atrium::common.no', $array['falseLabel'], $locale);
            $this->assertNotSame($array['trueLabel'], $array['falseLabel'], $locale);
        }

        app()->setLocale('ru');
        $this->assertSame('Да', BooleanColumn::make('active')->toArray()['trueLabel']);
    }

    public function test_own_labels_and_colours_replace_the_defaults(): void
    {
        $array = BooleanColumn::make('paid')->labels('Оплачен', 'Ждёт оплаты')->colors('blue', 'gray')->toArray();

        $this->assertSame('Оплачен', $array['trueLabel']);
        $this->assertSame('Ждёт оплаты', $array['falseLabel']);
        $this->assertSame('blue', $array['trueColor']);
        $this->assertSame('gray', $array['falseColor']);
    }

    public function test_it_can_be_drawn_as_text_instead_of_an_icon(): void
    {
        $this->assertFalse(BooleanColumn::make('a')->asText()->toArray()['asIcon']);
        $this->assertFalse(BooleanColumn::make('a')->asIcon(false)->toArray()['asIcon']);
        $this->assertTrue(BooleanColumn::make('a')->asText()->asIcon()->toArray()['asIcon']);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function values(): array
    {
        return [
            'true' => [true, true],
            'false' => [false, false],
            'one' => [1, true],
            'zero' => [0, false],
            'one as a string' => ['1', true],
            'zero as a string' => ['0', false],
            'a Bitrix option code' => ['Y', true],
            'null' => [null, false],
            'empty string' => ['', false],
            'empty array' => [[], false],
        ];
    }

    #[DataProvider('values')]
    public function test_the_value_is_shown_as_yes_or_no(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, BooleanColumn::make('active')->formatValue($value, null));
    }
}
