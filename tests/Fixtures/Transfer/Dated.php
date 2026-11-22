<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Transfer;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * Dates the model does not cast: they reach an export as the database wrote them.
 */
final class Dated extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'parcels';

    protected $guarded = [];

    public static function getAdminLabel(): string
    {
        return 'Dated';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('article')->label('Article'),
            DateTimeField::make('starts_at')->label('Starts at'),
            DateField::make('starts_on')->label('Starts on'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('article')->label('Article')];
    }
}
