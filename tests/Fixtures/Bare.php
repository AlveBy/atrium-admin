<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\TextField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A module that meets the Administrable contract and nothing more: no
 * InteractsWithAdmin, so none of the optional methods the panel asks for
 * with method_exists().
 */
final class Bare extends Model implements Administrable
{
    protected $table = 'posts';

    protected $fillable = ['name'];

    public static function getAdminLabel(): string
    {
        return 'Bare';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name')];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }

    public static function getAdministrationFilters(): array
    {
        return [];
    }

    public static function getAdministrationViews(): array
    {
        return [];
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'bares';
    }

    public static function getAdminRouteName(): string
    {
        return 'admin.bares';
    }

    public static function getAdminCreateLabel(): string
    {
        return 'Add bare';
    }

    public static function getDefaultSortColumn(): string
    {
        return 'id';
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAdminPerPage(): int
    {
        return 20;
    }

    public static function getCreateValidationRules(): array
    {
        return [];
    }

    public static function getUpdateValidationRules(): array
    {
        return [];
    }

    public function scopeAdminList(Builder $query): Builder
    {
        return $query;
    }

    public function canDelete(): bool
    {
        return true;
    }

    public function beforeAdminDelete(): bool
    {
        return true;
    }

    public function transformAdminData(array $data): array
    {
        return $data;
    }

    public function afterAdminSave(array $data): void {}

    public function getAdminTitle(): string
    {
        return (string) $this->name;
    }
}
