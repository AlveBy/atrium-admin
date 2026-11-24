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
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Views\KanbanView;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with a kanban board over a select field, its cards kept in order,
 * two cards to a page and three fields on a card.
 */
final class Task extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'tasks';

    protected $fillable = ['title', 'status', 'lane_id', 'rank', 'due_on', 'urgent'];

    protected function casts(): array
    {
        return ['rank' => 'integer', 'urgent' => 'boolean', 'due_on' => 'date'];
    }

    public static function getAdminLabel(): string
    {
        return 'Tasks';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('title')->label('Title')->required(),
            SelectField::make('status')->label('Status')->options(['todo' => 'To do', 'doing' => 'Doing', 'done' => 'Done'])->required(),
            BelongsToField::make('lane_id')->label('Lane')->model(Chapter::class),
            NumberField::make('rank')->label('Rank')->default(0),
            DateField::make('due_on')->label('Due'),
            CheckboxField::make('urgent')->label('Urgent'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('title')->label('Title')->searchable()];
    }

    public static function getAdministrationViews(): array
    {
        return [KanbanView::make('status')->title('title')->cards('lane_id', 'due_on', 'urgent')->order('rank')->perColumn(2)];
    }

    public function getAdminTitle(): string
    {
        return $this->title;
    }
}
