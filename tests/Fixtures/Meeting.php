<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Views\CalendarView;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with a calendar of records that start and end at a time.
 */
final class Meeting extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'meetings';

    protected $fillable = ['title', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public static function getAdminLabel(): string
    {
        return 'Meetings';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('title')->label('Title')->required(),
            DateTimeField::make('starts_at')->label('Starts'),
            DateTimeField::make('ends_at')->label('Ends'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('title')->label('Title')->searchable()];
    }

    public static function getAdministrationViews(): array
    {
        return [CalendarView::make('starts_at')->until('ends_at')->title('title')];
    }

    public function getAdminTitle(): string
    {
        return $this->title;
    }
}
