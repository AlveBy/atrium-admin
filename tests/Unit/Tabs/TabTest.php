<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Tabs;

use Atrium\Admin\Tabs\Tab;
use Atrium\Admin\Tests\TestCase;

final class TabTest extends TestCase
{
    public function test_a_new_tab_is_labelled_by_its_name_and_visible(): void
    {
        $tab = Tab::make('delivery');

        $this->assertSame('delivery', $tab->getName());
        $this->assertSame('delivery', $tab->getLabel());
        $this->assertSame(0, $tab->getOrder());
        $this->assertTrue($tab->isVisible());
        $this->assertSame([
            'name' => 'delivery',
            'label' => 'delivery',
            'icon' => null,
            'order' => 0,
            'visible' => true,
            'badge' => null,
        ], $tab->toArray());
    }

    public function test_every_setting_reaches_the_form(): void
    {
        $tab = Tab::make('delivery')->label('Доставка 🚚')->icon('truck')->order(-5)->badge('3');

        $this->assertSame('Доставка 🚚', $tab->getLabel());
        $this->assertSame(-5, $tab->getOrder());
        $this->assertSame([
            'name' => 'delivery',
            'label' => 'Доставка 🚚',
            'icon' => 'truck',
            'order' => -5,
            'visible' => true,
            'badge' => '3',
        ], $tab->toArray());
    }

    public function test_a_badge_can_be_taken_away(): void
    {
        $this->assertNull(Tab::make('a')->badge('new')->badge(null)->toArray()['badge']);
    }

    public function test_a_tab_can_be_hidden_and_shown_again(): void
    {
        $this->assertFalse(Tab::make('a')->hidden()->isVisible());
        $this->assertFalse(Tab::make('a')->visible(false)->toArray()['visible']);
        $this->assertTrue(Tab::make('a')->hidden()->visible()->isVisible());
    }

    public function test_json_is_the_same_as_the_array(): void
    {
        $tab = Tab::make('seo')->label('SEO')->order(10);

        $this->assertSame($tab->toArray(), $tab->jsonSerialize());
        $this->assertSame(json_encode($tab->toArray()), json_encode($tab));
    }
}
