<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Tabs;

use Atrium\Admin\Tabs\Tab;
use Atrium\Admin\Tabs\TabRegistry;
use Atrium\Admin\Tests\TestCase;

final class TabRegistryTest extends TestCase
{
    public function test_the_default_tabs_are_those_of_a_bitrix_element_in_order(): void
    {
        $tabs = array_map(fn (Tab $tab): array => $tab->toArray(), TabRegistry::getDefaultTabs());

        $this->assertSame([
            ['name' => 'main', 'label' => 'General', 'icon' => 'document-text', 'order' => 0, 'visible' => true, 'badge' => null],
            ['name' => 'seo', 'label' => 'SEO', 'icon' => 'search', 'order' => 10, 'visible' => true, 'badge' => null],
            ['name' => 'additional', 'label' => 'Additional', 'icon' => 'cog', 'order' => 20, 'visible' => true, 'badge' => null],
            ['name' => 'custom_fields', 'label' => 'Custom fields', 'icon' => 'collection', 'order' => 30, 'visible' => true, 'badge' => null],
            ['name' => 'element_properties', 'label' => 'Element properties', 'icon' => 'puzzle', 'order' => 40, 'visible' => true, 'badge' => null],
        ], $tabs);
    }

    public function test_the_section_tabs_start_with_the_section_itself(): void
    {
        $tabs = array_map(fn (Tab $tab): array => $tab->toArray(), TabRegistry::getSectionTabs());

        $this->assertSame([
            ['name' => 'main', 'label' => 'Section', 'icon' => 'folder', 'order' => 0, 'visible' => true, 'badge' => null],
            ['name' => 'seo', 'label' => 'SEO', 'icon' => 'search', 'order' => 10, 'visible' => true, 'badge' => null],
            ['name' => 'additional', 'label' => 'Additional', 'icon' => 'cog', 'order' => 20, 'visible' => true, 'badge' => null],
            ['name' => 'custom_fields', 'label' => 'Custom fields', 'icon' => 'collection', 'order' => 30, 'visible' => true, 'badge' => null],
            ['name' => 'element_properties', 'label' => 'Element properties', 'icon' => 'puzzle', 'order' => 40, 'visible' => true, 'badge' => null],
        ], $tabs);
    }

    public function test_the_tab_labels_are_translated_into_every_language(): void
    {
        $keys = ['main', 'seo', 'additional', 'custom_fields', 'element_properties'];

        foreach (array_keys(config('atrium.locales')) as $locale) {
            app()->setLocale($locale);

            $labels = array_map(fn (Tab $tab): string => $tab->getLabel(), TabRegistry::getDefaultTabs());
            $section = TabRegistry::getSectionTabs()[0]->getLabel();

            $this->assertSame(array_map(fn (string $key): string => __("atrium::fields.tabs.{$key}"), $keys), $labels, $locale);
            $this->assertSame(__('atrium::fields.tabs.section'), $section, $locale);

            foreach ([...$labels, $section] as $label) {
                $this->assertStringStartsNotWith('atrium::', $label, $locale);
                $this->assertNotSame('', trim($label), $locale);
            }
        }
    }

    public function test_the_names_are_the_constants(): void
    {
        $this->assertSame(
            [TabRegistry::MAIN, TabRegistry::SEO, TabRegistry::ADDITIONAL, TabRegistry::CUSTOM_FIELDS, TabRegistry::ELEMENT_PROPERTIES],
            array_map(fn (Tab $tab): string => $tab->getName(), TabRegistry::getDefaultTabs()),
        );
    }

    public function test_tabs_without_fields_are_left_out_whether_objects_or_arrays(): void
    {
        $tabs = [
            Tab::make('main'),
            Tab::make('seo'),
            ['name' => 'delivery'],
            ['name' => 'empty'],
            ['label' => 'no name'],
            Tab::make('missing'),
        ];

        $kept = TabRegistry::filterEmptyTabs($tabs, [
            'main' => ['name'],
            'seo' => [],
            'delivery' => ['price'],
            'empty' => [],
            '' => ['orphan'],
        ]);

        $this->assertSame([0, 1, 2], array_keys($kept), 'the list is renumbered');
        $this->assertSame($tabs[0], $kept[0]);
        $this->assertSame(['name' => 'delivery'], $kept[1]);
        $this->assertSame(['label' => 'no name'], $kept[2], 'a tab without a name takes the fields filed under no tab');
    }

    public function test_nothing_is_left_when_there_are_no_fields(): void
    {
        $this->assertSame([], TabRegistry::filterEmptyTabs(TabRegistry::getDefaultTabs(), []));
        $this->assertSame([], TabRegistry::filterEmptyTabs([], ['main' => ['name']]));
    }

    public function test_tabs_are_sorted_by_order_whether_objects_or_arrays(): void
    {
        $sorted = TabRegistry::sortTabs([
            Tab::make('c')->order(30),
            ['name' => 'a', 'order' => -10],
            ['name' => 'unordered'],
            Tab::make('b')->order(10),
        ]);

        $names = array_map(fn ($tab): string => $tab instanceof Tab ? $tab->getName() : $tab['name'], $sorted);

        $this->assertSame(['a', 'unordered', 'b', 'c'], $names);
        $this->assertSame([], TabRegistry::sortTabs([]));
    }

    public function test_a_tab_is_found_by_its_name(): void
    {
        $tabs = [['name' => 'seo'], Tab::make('main'), Tab::make('seo')->label('Found')];

        $this->assertSame('Found', TabRegistry::getTab('seo', $tabs)?->getLabel());
        $this->assertSame('main', TabRegistry::getTab('main', $tabs)?->getName());
    }

    public function test_an_unknown_name_or_an_array_tab_is_not_found(): void
    {
        $this->assertNull(TabRegistry::getTab('delivery', TabRegistry::getDefaultTabs()));
        $this->assertNull(TabRegistry::getTab('seo', [['name' => 'seo']]));
        $this->assertNull(TabRegistry::getTab('main', []));
        $this->assertNull(TabRegistry::getTab('MAIN', TabRegistry::getDefaultTabs()), 'names are case-sensitive');
    }
}
