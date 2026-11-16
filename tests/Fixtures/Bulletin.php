<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * Posts whose list can be narrowed from the address: `?name=…&active=…`.
 */
final class Bulletin extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'posts';

    protected $fillable = ['name', 'active', 'sort'];

    public static function getAdminLabel(): string
    {
        return 'Bulletins';
    }

    public static function getAdministrationFilters(): array
    {
        return ['name', 'active'];
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            NumberField::make('sort')->label('Sort')->default(500),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('name')->label('Name')->searchable(),
            TextColumn::make('sort')->label('Sort')->sortable(),
        ];
    }
}
