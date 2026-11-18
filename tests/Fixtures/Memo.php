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
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A record is written once: it is created here and then only read and deleted.
 */
final class Memo extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'posts';

    protected $fillable = ['name'];

    public static function getAdminLabel(): string
    {
        return 'Memos';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name')->required()];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }

    public static function isEditableInAdmin(): bool
    {
        return false;
    }
}
