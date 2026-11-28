<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\LayoutField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A module whose records are built of blocks.
 */
final class Landing extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'landings';

    protected $fillable = ['name', 'layout'];

    protected function casts(): array
    {
        return ['layout' => 'array'];
    }

    public static function getAdminLabel(): string
    {
        return 'Landings';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Title')->required(),
            LayoutField::make('layout')->label('Blocks'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Title')];
    }
}
