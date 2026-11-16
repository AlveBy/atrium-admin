<?php

declare(strict_types=1);

namespace Atrium\Admin\Traits;

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
     * Подпись кнопки «добавить» для этой сущности.
     *
     * У инфоблоков Битрикса она своя и уже в нужном падеже («Добавить товар»,
     * «Добавить услугу», «Добавить вакансию») — генератор переопределяет этот
     * метод текстом из настроек инфоблока. Здесь общий запасной вариант.
     */
    public static function getAdminCreateLabel(): string
    {
        return 'Добавить элемент';
    }

    /**
     * Get filters for admin list
     */
    public static function getAdministrationFilters(): array
    {
        return [];
    }

    /**
     * Get actions for admin list
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
            ['label' => $this->exists ? $this->getAdminTitle() : 'Создание'],
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
