<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\BelongsToSite;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Views\CalendarView;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with a calendar of one-day records of a date field, with no title
 * of its own for them, and a site each.
 */
final class Holiday extends Model implements Administrable
{
    use BelongsToSite, InteractsWithAdmin;

    protected $table = 'holidays';

    protected $fillable = ['name', 'on_day', 'site_id'];

    protected function casts(): array
    {
        return ['on_day' => 'date'];
    }

    public static function getAdminLabel(): string
    {
        return 'Holidays';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            DateField::make('on_day')->label('Day'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')->searchable()];
    }

    public static function getAdministrationViews(): array
    {
        return [CalendarView::make('on_day')];
    }
}
