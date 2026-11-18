<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\GalleryField;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\MapField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\SlugField;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * An on/off option called without an argument means "on", and called with
 * false means "off", whatever it was before.
 */
final class FieldSwitchesTest extends FieldTestCase
{
    /**
     * @return array<string, array{\Closure():Field, string, string}>
     */
    public static function switches(): array
    {
        return [
            'belongs to: searchable' => [fn (): Field => BelongsToField::make('a'), 'searchable', 'searchable'],
            'belongs to: clearable' => [fn (): Field => BelongsToField::make('a'), 'clearable', 'clearable'],
            'select: clearable' => [fn (): Field => SelectField::make('a'), 'clearable', 'clearable'],
            'gallery: sortable' => [fn (): Field => GalleryField::make('a'), 'sortable', 'sortable'],
            'image: preview' => [fn (): Field => ImageField::make('a'), 'showPreview', 'showPreview'],
            'map: address search' => [fn (): Field => MapField::make('a'), 'searchEnabled', 'searchEnabled'],
            'slug: auto-generation' => [fn (): Field => SlugField::make('a')->baseUrl('https://x.example'), 'autoGenerate', 'autoGenerate'],
            'slug: preview' => [fn (): Field => SlugField::make('a')->baseUrl('https://x.example'), 'showPreview', 'showPreview'],
        ];
    }

    #[DataProvider('switches')]
    public function test_a_switch_called_without_an_argument_turns_the_option_on(\Closure $make, string $method, string $prop): void
    {
        $this->assertTrue($make()->{$method}(false)->{$method}()->toArray()[$prop]);
        $this->assertFalse($make()->{$method}(true)->{$method}(false)->toArray()[$prop]);
    }
}
