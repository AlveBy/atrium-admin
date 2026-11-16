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
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Pages whose translatable code is hidden from the list's rows.
 */
final class Notice extends Model implements Administrable
{
    use InteractsWithAdmin, Translatable;

    protected $table = 'pages';

    protected $fillable = ['name', 'code'];

    protected $hidden = ['code'];

    protected array $translatable = ['name', 'code'];

    public static function getAdminLabel(): string
    {
        return 'Notices';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Title')->required(),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('name')->label('Title'),
        ];
    }
}
