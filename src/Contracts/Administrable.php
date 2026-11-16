<?php

declare(strict_types=1);

namespace Atrium\Admin\Contracts;

use Atrium\Admin\Columns\Column;
use Atrium\Admin\Fields\Field;
use Illuminate\Database\Eloquent\Builder;

interface Administrable
{
    /**
     * Get admin label for this model (shown in menu, breadcrumbs)
     */
    public static function getAdminLabel(): string;

    /**
     * Get fields for admin form
     *
     * @return Field[]
     */
    public static function getAdministrationFields(): array;

    /**
     * Get columns for admin list
     *
     * @return Column[]
     */
    public static function getAdministrationListColumns(): array;

    /** URL segment and route-name key of the module: /admin/{prefix}. */
    public static function getAdminRoutePrefix(): string;

    public static function getAdminRouteName(): string;

    public static function getAdminCreateLabel(): string;

    public static function getDefaultSortColumn(): string;

    /** @return 'asc'|'desc'|string */
    public static function getDefaultSortDirection(): string;

    public static function getAdminPerPage(): int;

    /** @return array<string, mixed> */
    public static function getCreateValidationRules(): array;

    /** @return array<string, mixed> */
    public static function getUpdateValidationRules(): array;

    /**
     * Narrow or eager-load the admin list query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeAdminList(Builder $query): Builder;

    public function canDelete(): bool;

    /** Return false to cancel the delete. */
    public function beforeAdminDelete(): bool;

    /**
     * Last chance to reshape validated form data before fill().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformAdminData(array $data): array;

    /** @param  array<string, mixed>  $data */
    public function afterAdminSave(array $data): void;
}
