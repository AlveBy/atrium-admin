<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Modules;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * The model of the acme/atrium-labels module package (Modules/labels).
 *
 * @property int $id
 * @property string $name
 */
final class Label extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'labels';

    protected $fillable = ['name', 'code'];

    public static function module(): Module
    {
        return Module::fromPath(__DIR__.'/labels') ?? throw new \RuntimeException('The labels fixture is not a module.');
    }

    public static function getAdminLabel(): string
    {
        return 'Labels';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name')->required()];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }
}
