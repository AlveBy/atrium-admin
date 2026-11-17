<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Columns;

use Atrium\Admin\Columns\BooleanColumn;
use Atrium\Admin\Columns\Column;
use Atrium\Admin\Columns\DateColumn;
use Atrium\Admin\Columns\ImageColumn;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ColumnTest extends TestCase
{
    public function test_a_new_column_is_plain_visible_and_unsorted(): void
    {
        $this->assertSame([
            'name' => 'title',
            'type' => 'text',
            'component' => 'textColumn',
            'label' => '',
            'sortable' => false,
            'searchable' => false,
            'width' => null,
            'align' => null,
            'visible' => true,
            'limit' => null,
            'default' => '-',
            'copyable' => false,
            'url' => null,
            'urlFrom' => null,
        ], TextColumn::make('title')->toArray());
    }

    public function test_every_setting_reaches_the_list(): void
    {
        $column = TextColumn::make('title')
            ->label('Заголовок')
            ->sortable()
            ->searchable()
            ->width('12rem')
            ->align('left');

        $this->assertSame('title', $column->getName());
        $this->assertSame([
            'name' => 'title',
            'type' => 'text',
            'component' => 'textColumn',
            'label' => 'Заголовок',
            'sortable' => true,
            'searchable' => true,
            'width' => '12rem',
            'align' => 'left',
            'visible' => true,
        ], array_slice($column->toArray(), 0, 9));
    }

    public function test_sorting_and_searching_can_be_switched_off_again(): void
    {
        $array = TextColumn::make('title')->sortable()->sortable(false)->searchable()->searchable(false)->toArray();

        $this->assertFalse($array['sortable']);
        $this->assertFalse($array['searchable']);
    }

    /**
     * @return array<string, array{\Closure(Column):Column, string}>
     */
    public static function alignments(): array
    {
        return [
            'center' => [fn (Column $c): Column => $c->alignCenter(), 'center'],
            'right' => [fn (Column $c): Column => $c->alignRight(), 'right'],
            'anything else as given' => [fn (Column $c): Column => $c->align('justify'), 'justify'],
        ];
    }

    #[DataProvider('alignments')]
    public function test_the_alignment_is_passed_on(\Closure $align, string $expected): void
    {
        $this->assertSame($expected, $align(TextColumn::make('price'))->toArray()['align']);
    }

    public function test_a_column_can_be_hidden_and_shown(): void
    {
        $this->assertFalse(TextColumn::make('a')->hidden()->toArray()['visible']);
        $this->assertFalse(TextColumn::make('a')->visible(false)->toArray()['visible']);
        $this->assertTrue(TextColumn::make('a')->hidden()->visible()->toArray()['visible']);
    }

    /**
     * @return array<string, array{class-string<Column>, string, string}>
     */
    public static function types(): array
    {
        return [
            'text' => [TextColumn::class, 'text', 'textColumn'],
            'boolean' => [BooleanColumn::class, 'boolean', 'booleanColumn'],
            'date' => [DateColumn::class, 'date', 'dateColumn'],
            'image' => [ImageColumn::class, 'image', 'imageColumn'],
        ];
    }

    /**
     * @param  class-string<Column>  $class
     */
    #[DataProvider('types')]
    public function test_each_column_names_its_type_and_vue_component(string $class, string $type, string $component): void
    {
        $column = $class::make('x');

        $this->assertSame($type, $column->getType());
        $this->assertSame($component, $column->getComponent());
        $this->assertSame($type, $column->toArray()['type']);
        $this->assertSame($component, $column->toArray()['component']);
    }

    public function test_a_custom_column_shows_the_value_as_it_is_and_adds_no_props(): void
    {
        $custom = new class extends Column
        {
            public function getType(): string
            {
                return 'badge';
            }
        };

        $column = $custom::make('status');

        $this->assertSame('badgeColumn', $column->getComponent());
        $this->assertSame(['name', 'type', 'component', 'label', 'sortable', 'searchable', 'width', 'align', 'visible'], array_keys($column->toArray()));
        $this->assertSame('<b>new</b>', $column->formatValue('<b>new</b>', null));
        $this->assertNull($column->formatValue(null, null));
        $this->assertSame([1, 2], $column->formatValue([1, 2], null));
    }

    public function test_json_is_the_same_as_the_array(): void
    {
        $column = BooleanColumn::make('active')->label('Active')->sortable();

        $this->assertSame($column->toArray(), $column->jsonSerialize());
        $this->assertSame(json_encode($column->toArray()), json_encode($column));
    }
}
