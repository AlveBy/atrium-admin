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
 * A record that comes with a value of its own before anything is typed: a
 * new one's history still lists it.
 */
final class Draft extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'posts';

    protected $fillable = ['name', 'sort'];

    protected $attributes = ['sort' => 7];

    public static function getAdminLabel(): string
    {
        return 'drafts';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            NumberField::make('sort')->label('Sort'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }
}
