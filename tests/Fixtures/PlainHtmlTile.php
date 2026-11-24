<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Dashboard\HtmlGadget;

/**
 * An HTML tile that hands its view nothing of its own.
 */
final class PlainHtmlTile extends HtmlGadget
{
    public function title(): string
    {
        return 'Plain';
    }

    protected function view(): string
    {
        return 'acme::plain-tile';
    }
}
