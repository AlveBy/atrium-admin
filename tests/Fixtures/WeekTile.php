<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Dashboard\HtmlGadget;

/**
 * A package's tile drawn by a Blade view; what it hands the view is
 * markup on purpose, to see it escaped.
 */
final class WeekTile extends HtmlGadget
{
    public function title(): string
    {
        return 'Time this week';
    }

    protected function view(): string
    {
        return 'acme::week-tile';
    }

    protected function viewData(): array
    {
        return ['hours' => '<b>12</b>'];
    }
}
