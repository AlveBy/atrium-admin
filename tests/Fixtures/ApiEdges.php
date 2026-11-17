<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\TreeSelectField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * Fields that come with a note of their own before the API adds its text:
 * the API documentation joins the two.
 */
final class ApiEdges extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'api_edges';

    public static function getAdminLabel(): string
    {
        return 'Edges';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            CheckboxField::make('agree')->label('Agree')->checkedValue('Y')->onlyOnCreate(),
            MultiSelectField::make('tags')->label('Tags')->options(['a' => 'A'])->onlyOnEdit(),
            TreeSelectField::make('section_id')->label('Section')->model(ApiAisle::class)->onlyOnCreate(),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }
}
