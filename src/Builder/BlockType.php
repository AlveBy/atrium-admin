<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Builder;

use Atrium\Admin\Fields\Field;

/**
 * A kind of block of the site builder: what the editor asks for and how the
 * site draws it. A module puts its types in the registry of the hooks:
 *
 *     $hooks->register(BlockTypes::REGISTRY, 'price', new PriceBlock);
 *
 * The key is what a page's tree keeps as the block's `type`, so it must not
 * change once pages use it.
 */
abstract class BlockType
{
    /** The name on the editor's list of blocks. */
    abstract public function label(): string;

    /** The Blade view that draws one block. */
    abstract public function view(): string;

    /**
     * The settings of a block: the same fields a form is made of. Their names
     * are the keys of the block's `settings`.
     *
     * @return array<int,Field>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * The settings that are written per content language: the tree keeps
     * `{"en": …, "de": …}` for them and the view gets the visitor's one.
     *
     * @return array<int,string>
     */
    public function translatable(): array
    {
        return [];
    }

    /**
     * What the view gets, made of the stored settings (translatable ones are
     * already in the visitor's language, or null). Null when there is nothing
     * to draw: the block is left out of the page, its files too. Settings can
     * be missing or of any shape; a tree may be older than the type.
     *
     * @param  array<string,mixed>  $settings
     * @return array<string,mixed>|null
     */
    public function data(array $settings): ?array
    {
        return $settings;
    }

    /**
     * CSS files of this type, as paths on disk. A page with such a block gets
     * their text once; a page without one gets nothing.
     *
     * @return array<int,string>
     */
    public function styles(): array
    {
        return [];
    }

    /**
     * JavaScript files of this type, as paths on disk, added the same way.
     *
     * @return array<int,string>
     */
    public function scripts(): array
    {
        return [];
    }

    /**
     * Whether the type works now (its module is installed). An unavailable
     * one is not offered, and the blocks of it on pages are left out.
     */
    public function available(): bool
    {
        return true;
    }
}
