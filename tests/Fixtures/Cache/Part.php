<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cache;

use Atrium\Admin\Cache\CachesRecords;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Contracts\Cacheable;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A record whose card shows records of its own class: the ones that point at
 * the same parent are dropped with it.
 */
final class Part extends Model implements Administrable, Cacheable
{
    use CachesRecords, InteractsWithAdmin;

    public $timestamps = false;

    protected $table = 'cache_parts';

    protected $fillable = ['name', 'parent_id'];

    public static function getAdminLabel(): string
    {
        return 'Parts';
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'parts';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name'),
            BelongsToField::make('parent_id')->label('Parent')->model(self::class),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [];
    }
}
