<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Dashboard\Gadget;
use RuntimeException;

/**
 * A dashboard tile that shows its options, and breaks on request: while
 * being built or while drawing its data.
 */
final class Tile extends Gadget
{
    public function __construct(array $options = [])
    {
        if ($options['broken_config'] ?? false) {
            throw new RuntimeException('Tile cannot be built.');
        }

        parent::__construct($options);
    }

    public function component(): string
    {
        return 'TileGadget';
    }

    public function title(): string
    {
        return (string) $this->option('title', 'Tile');
    }

    public function data(): array
    {
        if ($this->option('broken_data', false)) {
            throw new RuntimeException('Tile data is broken.');
        }

        return ['options' => $this->options];
    }
}
