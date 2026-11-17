<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Columns;

use Atrium\Admin\Columns\ImageColumn;
use Atrium\Admin\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ImageColumnTest extends TestCase
{
    public function test_by_default_it_is_a_square_thumbnail_without_a_placeholder(): void
    {
        $array = ImageColumn::make('picture')->toArray();

        $this->assertSame(50, $array['width']);
        $this->assertSame(50, $array['height']);
        $this->assertFalse($array['rounded']);
        $this->assertNull($array['defaultImage']);
    }

    public function test_the_thumbnail_size_can_be_set_as_a_square_or_a_rectangle(): void
    {
        $square = ImageColumn::make('picture')->size(80)->toArray();
        $rectangle = ImageColumn::make('picture')->dimensions(120, 40)->toArray();

        $this->assertSame([80, 80], [$square['width'], $square['height']]);
        $this->assertSame([120, 40], [$rectangle['width'], $rectangle['height']]);
    }

    public function test_the_thumbnail_can_be_rounded(): void
    {
        $this->assertTrue(ImageColumn::make('a')->rounded()->toArray()['rounded']);
        $this->assertTrue(ImageColumn::make('a')->circular()->toArray()['rounded']);
        $this->assertFalse(ImageColumn::make('a')->rounded()->rounded(false)->toArray()['rounded']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function empties(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
        ];
    }

    #[DataProvider('empties')]
    public function test_a_record_without_a_picture_shows_the_placeholder(mixed $value): void
    {
        $this->assertNull(ImageColumn::make('picture')->formatValue($value, null));

        $column = ImageColumn::make('picture')->defaultImage('/img/none.svg');

        $this->assertSame('/img/none.svg', $column->toArray()['defaultImage']);
        $this->assertSame('/img/none.svg', $column->formatValue($value, null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function absoluteUrls(): array
    {
        return [
            'http' => ['http://cdn.example.com/a.jpg'],
            'https' => ['https://cdn.example.com/a.jpg'],
        ];
    }

    #[DataProvider('absoluteUrls')]
    public function test_an_absolute_address_is_shown_as_it_is(string $url): void
    {
        $this->assertSame($url, ImageColumn::make('picture')->defaultImage('/none.svg')->formatValue($url, null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function storedPaths(): array
    {
        return [
            'plain' => ['photos/a.jpg'],
            'Cyrillic name' => ['photos/фото.jpg'],
            'Chinese name' => ['photos/照片.png'],
            'nested' => ['2026/03/05/a.webp'],
        ];
    }

    #[DataProvider('storedPaths')]
    public function test_a_stored_path_is_served_from_public_storage(string $path): void
    {
        $this->assertSame(asset('storage/'.$path), ImageColumn::make('picture')->formatValue($path, null));
        $this->assertStringEndsWith('/storage/'.$path, ImageColumn::make('picture')->formatValue($path, null));
    }
}
