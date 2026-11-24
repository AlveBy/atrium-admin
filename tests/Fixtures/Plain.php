<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Views\KanbanView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A module that meets the Administrable contract and nothing more, with a
 * board over it and no title of its own to show on a card.
 */
final class Plain extends Model implements Administrable
{
    protected $table = 'tickets';

    protected $fillable = ['name', 'lane_id'];

    public static function getAdminLabel(): string
    {
        return 'Plain';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name'), BelongsToField::make('lane_id')->label('Lane')->model(Chapter::class)];
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
        return [KanbanView::make('lane_id')];
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'plains';
    }

    public static function getAdminRouteName(): string
    {
        return 'admin.plains';
    }

    public static function getAdminCreateLabel(): string
    {
        return 'Add plain';
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
}
