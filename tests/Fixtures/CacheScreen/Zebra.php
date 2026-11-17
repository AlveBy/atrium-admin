<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\CacheScreen;

use Atrium\Admin\Cache\CachesRecords;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Contracts\Cacheable;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A second and third cacheable module for the Cache screen: its list of
 * modules has to be sorted by label, not by the order modules were added in.
 */
final class Zebra extends Model implements Administrable, Cacheable
{
    use CachesRecords, InteractsWithAdmin;

    protected $table = 'cache_screen_zebras';

    public static function getAdminLabel(): string
    {
        return 'Zebras';
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'zebras';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name')];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }
}
