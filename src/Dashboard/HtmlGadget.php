<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Dashboard;

/**
 * A tile drawn by a Blade view on the server: what a package puts on the
 * dashboard without building any Vue. The panel frames it with the title
 * and shows the HTML as it is, so the view escapes what it prints ({{ }}).
 *
 *     final class WeekGadget extends HtmlGadget
 *     {
 *         public function title(): string { return __('acme-time::gadget.week'); }
 *         protected function view(): string { return 'acme-time::gadget'; }
 *         protected function viewData(): array { return ['hours' => Hours::thisWeek()]; }
 *     }
 *
 *     Hook::register('atrium.ui.gadgets', 'time.week', WeekGadget::class);
 */
abstract class HtmlGadget extends Gadget
{
    /** The Blade view that draws the tile. */
    abstract protected function view(): string;

    /**
     * What the view gets; the tile's options are there as `$options`.
     *
     * @return array<string,mixed>
     */
    protected function viewData(): array
    {
        return [];
    }

    final public function component(): string
    {
        return 'HtmlGadget';
    }

    /**
     * @return array{html: string}
     */
    final public function data(): array
    {
        return ['html' => view($this->view(), $this->viewData() + ['options' => $this->options])->render()];
    }
}
