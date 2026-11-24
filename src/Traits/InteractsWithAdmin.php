<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Actions\BulkAction;
use Atrium\Admin\Actions\CreateFromRecord;
use Atrium\Admin\Actions\LinkAction;
use Atrium\Admin\Actions\RecordAction;
use Atrium\Admin\Filters\Filter;
use Atrium\Admin\Views\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait InteractsWithAdmin
{
    /**
     * Get admin route prefix
     */
    public static function getAdminRoutePrefix(): string
    {
        return Str::plural(Str::snake(class_basename(static::class)));
    }

    /**
     * Get admin route name
     */
    public static function getAdminRouteName(): string
    {
        return 'admin.'.static::getAdminRoutePrefix();
    }

    /**
     * Label of the "add" button for this entity.
     *
     * Bitrix iblocks have their own, already in the right grammatical case
     * ("Add product", "Add service") — the generator overrides this method with
     * the text from the iblock settings. This is the generic fallback.
     */
    public static function getAdminCreateLabel(): string
    {
        return __('atrium::resource.add_item');
    }

    /**
     * Columns the list can be narrowed by from the address: `['module']`
     * lets `?module=…` show only those records, and the create form opened
     * from that list starts with the same value. A Filter
     * (SelectFilter, BooleanFilter) is drawn above the list as well.
     *
     * @return array<int,string|Filter>
     */
    public static function getAdministrationFilters(): array
    {
        return [];
    }

    /**
     * Buttons on a record, in the list and on its form: CreateFromRecord
     * opens the create form filled from the record (a subpage, a copy),
     * LinkAction an address, a RecordAction runs on the server. Other
     * modules add theirs through the `atrium.module.actions` hook.
     *
     * @return array<int,CreateFromRecord|LinkAction|RecordAction>
     */
    public static function getAdministrationActions(): array
    {
        return [];
    }

    /**
     * Buttons over the records selected in the list, after the standard
     * "Delete". Other modules add theirs through the
     * `atrium.module.bulk_actions` hook.
     *
     * @return array<int,BulkAction>
     */
    public static function getAdministrationBulkActions(): array
    {
        return [];
    }

    /**
     * Other ways to look at the list besides the table: a KanbanView (cards
     * in the columns of a select) or a CalendarView (records on the days of
     * a date). A module that returns one gets a "Table / Kanban / Calendar"
     * switcher above its list, and the administrator's choice is kept.
     * Other modules add theirs through the `atrium.module.views` hook.
     *
     * @return array<int,View>
     */
    public static function getAdministrationViews(): array
    {
        return [];
    }

    /**
     * Get default sort column
     */
    public static function getDefaultSortColumn(): string
    {
        return 'id';
    }

    /**
     * Get default sort direction
     */
    public static function getDefaultSortDirection(): string
    {
        return 'desc';
    }

    /**
     * Get items per page for admin list
     */
    public static function getAdminPerPage(): int
    {
        return 20;
    }

    /**
     * Scope for admin list query
     */
    public function scopeAdminList(Builder $query): Builder
    {
        return $query;
    }

    /**
     * Get validation rules for create
     */
    public static function getCreateValidationRules(): array
    {
        return [];
    }

    /**
     * Get validation rules for update
     */
    public static function getUpdateValidationRules(): array
    {
        return static::getCreateValidationRules();
    }

    /**
     * Boot the trait
     */
    public static function bootInteractsWithAdmin(): void
    {
        // Add any model events if needed
    }

    /**
     * Transform data before saving (for admin form)
     */
    public function transformAdminData(array $data): array
    {
        return $data;
    }

    /**
     * After save hook for admin
     */
    public function afterAdminSave(array $data): void
    {
        // Override in model if needed
    }

    /**
     * Before delete hook for admin
     */
    public function beforeAdminDelete(): bool
    {
        return true;
    }

    /**
     * Whether the admin may add records of this module. False for records that
     * come from elsewhere (form submissions, a log): no "Add" button, and the
     * create and store routes answer 403, in the API too.
     */
    public static function isCreatableInAdmin(): bool
    {
        return true;
    }

    /**
     * Whether the admin may change records of this module. False opens a
     * record read-only, with no "Save", whatever the role allows.
     */
    public static function isEditableInAdmin(): bool
    {
        return true;
    }

    /**
     * Whether records of this module may be imported from CSV and Excel
     * files. They are created and changed the way the form does it, so a
     * module that is neither creatable nor editable has nothing to import.
     * Return false to take the module out of the import.
     */
    public static function isImportableInAdmin(): bool
    {
        return static::isCreatableInAdmin() && static::isEditableInAdmin();
    }

    /**
     * Whether the list of this module may be exported to CSV and Excel.
     * Return false to take the module out of the export.
     */
    public static function isExportableInAdmin(): bool
    {
        return true;
    }

    /**
     * Whether changes of the records go to the activity log (who changed
     * what and when) and get a "History" button on the form. Return false for
     * records that change too often to tell anything, or that log
     * themselves.
     */
    public static function hasAdminHistory(): bool
    {
        return true;
    }

    /**
     * Whether the records keep a version on every save, to compare two and put
     * an old one back (docs/history.md). Only where they have a history.
     */
    public static function hasAdminRevisions(): bool
    {
        return true;
    }

    /**
     * Whether the module holds content: what editors write and the site
     * shows or sells (pages, products, news, the sites themselves). False
     * for service records the panel keeps for itself: logs, audit results,
     * form submissions, languages, administrators. Only a content module
     * can get custom fields and be picked as the target of a link or of a
     * review; service modules stay in the menu, the search, the API and the
     * export.
     */
    public static function isContentModule(): bool
    {
        return true;
    }

    /**
     * The columns that tell an imported row which record it is, besides the
     * id: the first one the file has is the default. A module whose records
     * are known by something other than their code (an article number, an
     * e-mail address) lists it first.
     *
     * @return array<int,string>
     */
    public static function getImportKeys(): array
    {
        return ['id', 'code'];
    }

    /**
     * Can be edited in admin
     */
    public function canEdit(): bool
    {
        return true;
    }

    /**
     * Can be deleted in admin
     */
    public function canDelete(): bool
    {
        return true;
    }

    /**
     * Get breadcrumbs for admin
     */
    public function getAdminBreadcrumbs(): array
    {
        return [
            ['label' => static::getAdminLabel(), 'url' => route(static::getAdminRouteName().'.index')],
            ['label' => $this->exists ? $this->getAdminTitle() : __('atrium::resource.form.breadcrumb_create')],
        ];
    }

    /**
     * Get title for admin (shown in edit form)
     */
    public function getAdminTitle(): string
    {
        return $this->name ?? $this->title ?? "#{$this->id}";
    }
}
