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
use Atrium\Admin\Traits\HasAdminTabs;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with tabs that decides for itself which fields its form has.
 */
final class Brochure extends Model implements Administrable
{
    use HasAdminTabs, InteractsWithAdmin;

    protected $table = 'posts';

    protected $fillable = ['name'];

    public static function getAdminLabel(): string
    {
        return 'Brochures';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name')->required()];
    }

    public static function getAdminFieldsWithTabs(): array
    {
        return [...self::getAdministrationFields(), TextField::make('cover')->label('Cover')];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }
}
