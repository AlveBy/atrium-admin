<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Embeds;

use Atrium\Admin\Models\AdminUser;

/**
 * Something an editor can put into the text of a page and a template shows
 * in its place: a form, a block with the phone number, a list of products.
 *
 * A module registers its types once, when it boots:
 *
 *     Hook::register(Embeds::REGISTRY, 'form', new FormEmbed);
 *
 * The editor stores a placeholder in the HTML (`<div data-atrium-embed="form"
 * data-id="3"></div>`); Embeds::render() swaps it for what render() returns
 * every time the page is shown, so the embed is never stale in a cached text.
 */
interface Embed
{
    /** The name in the editor's "Insert" list, in the admin's language. */
    public function label(): string;

    /** Whether the type works at all: the module behind it is installed. */
    public function available(): bool;

    /** Whether this administrator may pick items of the type. */
    public function permitted(?AdminUser $user): bool;

    /**
     * What the picker offers for a few typed words (all for none).
     *
     * @return array<int,array{id:string,label:string}>
     */
    public function options(string $search): array;

    /**
     * The names of items by id, for the labelled chip in the editor.
     *
     * @param  array<int,string>  $ids
     * @return array<int,array{id:string,label:string}>
     */
    public function labels(array $ids): array;

    /**
     * The embed on the site. An id that points at nothing, an item that is
     * switched off: the empty string, never an error.
     */
    public function render(string $id): string;
}
