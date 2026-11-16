<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Actions\CreateFromRecord;
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
     * from that list starts with the same value.
     *
     * @return array<int,string>
     */
    public static function getAdministrationFilters(): array
    {
        return [];
    }

    /**
     * Buttons on a record, in the list and on its form: CreateFromRecord
     * opens the create form filled from the record (a subpage, a copy).
     *
     * @return array<int,CreateFromRecord>
     */
    public static function getAdministrationActions(): array
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
