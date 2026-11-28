<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Builder;

use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\SelectField;

/**
 * The tree a page of the site builder is made of (docs/site-builder.md):
 *
 *     {"version": 1, "sections": [
 *         {"id": "s1", "settings": {"width": "default", "tone": "plain"}, "columns": [
 *             {"id": "c1", "settings": {"span": 12}, "blocks": [
 *                 {"id": "b1", "type": "text", "settings": {"html": {"en": "<p>Hi</p>"}}}
 *             ]}
 *         ]}
 *     ]}
 *
 * One tree serves every language: translatable settings hold one value per
 * language. Ids are unique within the tree.
 */
final class Layout
{
    public const VERSION = 1;

    public const MAX_SECTIONS = 100;

    /** A row is a grid of twelve, so no more columns than that. */
    public const MAX_COLUMNS = 12;

    /** Blocks in one column. */
    public const MAX_BLOCKS = 100;

    public const ID = '/^[A-Za-z0-9_-]{1,40}$/D';

    public const WIDTHS = ['narrow', 'default', 'full'];

    public const TONES = ['plain', 'tinted'];

    /**
     * The sections of a stored tree, to draw them: none for a value that is
     * not a tree of this version.
     *
     * @return array<int,array<array-key,mixed>>
     */
    public static function sections(mixed $layout): array
    {
        return is_array($layout) && ($layout['version'] ?? null) === self::VERSION
            ? self::nodes($layout['sections'] ?? null)
            : [];
    }

    /**
     * The nodes of a list in a tree; anything else in it is left out.
     *
     * @return array<int,array<array-key,mixed>>
     */
    public static function nodes(mixed $list): array
    {
        return is_array($list) ? array_values(array_filter($list, is_array(...))) : [];
    }

    /**
     * The same text for the same tree, however its keys are ordered (a JSON
     * column of MySQL reorders them): tells whether a stored tree changed
     * since it was read.
     */
    public static function fingerprint(mixed $tree): string
    {
        return hash('sha256', serialize(self::sorted($tree)));
    }

    /**
     * The value with the keys of every map in it in order.
     */
    private static function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        ksort($value);

        return array_map(self::sorted(...), $value);
    }

    /**
     * @return array<int,Field>
     */
    public static function sectionFields(): array
    {
        return [
            SelectField::make('width')
                ->label(__('atrium::fields.layout.width'))
                ->options(self::options('widths', self::WIDTHS))
                ->default('default'),
            SelectField::make('tone')
                ->label(__('atrium::fields.layout.tone'))
                ->options(self::options('tones', self::TONES))
                ->default('plain'),
        ];
    }

    /**
     * @return array<int,Field>
     */
    public static function columnFields(): array
    {
        return [
            NumberField::make('span')
                ->label(__('atrium::fields.layout.span'))
                ->min(1)
                ->max(self::MAX_COLUMNS)
                ->rules('integer')
                ->default(self::MAX_COLUMNS),
        ];
    }

    /**
     * @param  array<int,string>  $values
     * @return array<string,string>
     */
    private static function options(string $group, array $values): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = __("atrium::fields.layout.{$group}.{$value}");
        }

        return $options;
    }
}
