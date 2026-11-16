<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Tabs\Tab;
use Atrium\Admin\Tabs\TabRegistry;

trait HasAdminTabs
{
    /**
     * Get tabs for admin forms
     * Override in model to customize
     */
    public static function getAdminTabs(): array
    {
        return TabRegistry::getDefaultTabs();
    }

    /**
     * Get all fields including SEO fields with tabs
     * Automatically adds SEO fields if model uses HasAdminSeo trait, and the
     * fields other modules add through the `atrium.module.fields` hook
     */
    public static function getAdminFieldsWithTabs(): array
    {
        $fields = static::getAdministrationFields();

        // Automatically add SEO fields if HasAdminSeo trait is used
        if (method_exists(static::class, 'getSeoAdminFields')) {
            $seoFields = static::getSeoAdminFields();

            // Set SEO tab for all SEO fields
            foreach ($seoFields as $field) {
                $field->tab(TabRegistry::SEO);
            }

            $fields = array_merge($fields, $seoFields);
        }

        // Fields added by packages (custom fields), on the tabs they chose.
        return Hook::apply('atrium.module.fields', $fields, static::class);
    }

    /**
     * Get fields grouped by tabs
     */
    public static function getFieldsGroupedByTabs(string $context = 'create'): array
    {
        $fields = static::getAdminFieldsWithTabs();
        $tabs = static::getAdminTabs();

        // Filter fields by context (create/edit)
        $fields = array_filter($fields, function ($field) use ($context) {
            if ($context === 'create') {
                return $field->shouldShowOnCreate();
            }

            return $field->shouldShowOnEdit();
        });

        // Group fields by tabs
        $grouped = [];
        foreach ($fields as $field) {
            $tabName = $field->getTab();
            if (! isset($grouped[$tabName])) {
                $grouped[$tabName] = [];
            }
            $grouped[$tabName][] = $field;
        }

        // Sort fields within tabs by tabOrder
        foreach ($grouped as $tabName => $tabFields) {
            usort($tabFields, fn ($a, $b) => $a->getTabOrder() <=> $b->getTabOrder());
            $grouped[$tabName] = $tabFields;
        }

        // Filter out empty tabs and sort by order
        $visibleTabs = TabRegistry::filterEmptyTabs($tabs, $grouped);
        $sortedTabs = TabRegistry::sortTabs($visibleTabs);

        return [
            'tabs' => array_values(array_map(fn ($t) => $t instanceof Tab ? $t->toArray() : $t, $sortedTabs)),
            'fields' => $grouped,
        ];
    }

    /**
     * Check if model uses tabs
     */
    public static function usesTabs(): bool
    {
        return true;
    }
}
