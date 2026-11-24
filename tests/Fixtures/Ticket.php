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
use Atrium\Admin\Traits\BelongsToSite;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Views\KanbanView;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with a board whose columns are the records of another module,
 * whose cards are in no order of their own and belong to a site each.
 */
final class Ticket extends Model implements Administrable
{
    use BelongsToSite, InteractsWithAdmin;

    protected $table = 'tickets';

    protected $fillable = ['name', 'lane_id', 'site_id'];

    public static function getAdminLabel(): string
    {
        return 'Tickets';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            BelongsToField::make('lane_id')->label('Lane')->model(Chapter::class),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')->searchable()];
    }

    public static function getAdministrationViews(): array
    {
        return [KanbanView::make('lane_id')];
    }
}
