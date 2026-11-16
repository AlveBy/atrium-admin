<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tabs;

class TabRegistry
{
    /**
     * Standard tab names (Bitrix style)
     */
    public const MAIN = 'main';

    public const SEO = 'seo';

    public const ADDITIONAL = 'additional';

    public const CUSTOM_FIELDS = 'custom_fields';

    public const ELEMENT_PROPERTIES = 'element_properties';

    /**
     * Get default tabs for admin forms
     */
    public static function getDefaultTabs(): array
    {
        return [
            Tab::make(self::MAIN)
                ->label(__('atrium::fields.tabs.main'))
                ->icon('document-text')
                ->order(0),

            Tab::make(self::SEO)
                ->label(__('atrium::fields.tabs.seo'))
                ->icon('search')
                ->order(10),

            Tab::make(self::ADDITIONAL)
                ->label(__('atrium::fields.tabs.additional'))
                ->icon('cog')
                ->order(20),

            Tab::make(self::CUSTOM_FIELDS)
                ->label(__('atrium::fields.tabs.custom_fields'))
                ->icon('collection')
                ->order(30),

            Tab::make(self::ELEMENT_PROPERTIES)
                ->label(__('atrium::fields.tabs.element_properties'))
                ->icon('puzzle')
                ->order(40),
        ];
    }

    /**
     * Get tabs for sections (without element properties)
     */
    public static function getSectionTabs(): array
    {
        return [
            Tab::make(self::MAIN)
                ->label(__('atrium::fields.tabs.section'))
                ->icon('folder')
                ->order(0),

            Tab::make(self::SEO)
                ->label(__('atrium::fields.tabs.seo'))
                ->icon('search')
                ->order(10),

            Tab::make(self::ADDITIONAL)
                ->label(__('atrium::fields.tabs.additional'))
                ->icon('cog')
                ->order(20),

            Tab::make(self::CUSTOM_FIELDS)
                ->label(__('atrium::fields.tabs.custom_fields'))
                ->icon('collection')
                ->order(30),

            Tab::make(self::ELEMENT_PROPERTIES)
                ->label(__('atrium::fields.tabs.element_properties'))
                ->icon('puzzle')
                ->order(40),
        ];
    }

    /**
     * Filter out tabs that have no fields
     */
    public static function filterEmptyTabs(array $tabs, array $groupedFields): array
    {
        return array_values(array_filter($tabs, function ($tab) use ($groupedFields) {
            $tabName = $tab instanceof Tab ? $tab->getName() : ($tab['name'] ?? '');

            return ! empty($groupedFields[$tabName] ?? []);
        }));
    }

    /**
     * Sort tabs by order
     */
    public static function sortTabs(array $tabs): array
    {
        usort($tabs, function ($a, $b) {
            $orderA = $a instanceof Tab ? $a->getOrder() : ($a['order'] ?? 0);
            $orderB = $b instanceof Tab ? $b->getOrder() : ($b['order'] ?? 0);

            return $orderA <=> $orderB;
        });

        return $tabs;
    }

    /**
     * Get tab by name
     */
    public static function getTab(string $name, array $tabs): ?Tab
    {
        foreach ($tabs as $tab) {
            if ($tab instanceof Tab && $tab->getName() === $name) {
                return $tab;
            }
        }

        return null;
    }
}
