<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Dashboard;

/**
 * Dashboard gadget — a tile on the admin start page.
 *
 * Works like the Bitrix desktop: the set of tiles comes from a setting
 * (`atrium.dashboard.gadgets`) and each tile decides what to show. The
 * difference: Bitrix kept the layout per user in b_user_option and let it be
 * dragged with the mouse; here it lives in the config.
 */
abstract class Gadget
{
    /**
     * @param  array<string,mixed>  $options  instance options from the config
     */
    public function __construct(protected array $options = []) {}

    /** Name of the Vue component that renders the tile. */
    abstract public function component(): string;

    /** Tile title. */
    abstract public function title(): string;

    /** Data for the component. */
    abstract public function data(): array;

    /**
     * Tile width: 'half' — half a row, 'full' — the whole row.
     */
    public function width(): string
    {
        return $this->option('width', 'full') === 'half' ? 'half' : 'full';
    }

    /**
     * A tile can be switched off without removing it from the config, and it
     * decides itself whether it has anything to show (e.g. its model was removed).
     */
    public function isVisible(): bool
    {
        return (bool) $this->option('enabled', true);
    }

    public function toArray(): array
    {
        return [
            'component' => $this->component(),
            'title' => $this->title(),
            'width' => $this->width(),
            'data' => $this->data(),
        ];
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
