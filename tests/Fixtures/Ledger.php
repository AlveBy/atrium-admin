<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Actions\CreateFromRecord;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A module whose records come from elsewhere: read and deleted, never
 * created or edited in the panel.
 */
final class Ledger extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'posts';

    protected $fillable = ['name'];

    public static function getAdminLabel(): string
    {
        return 'Ledger';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name')];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }

    public static function getAdministrationActions(): array
    {
        return [CreateFromRecord::make('copy')->label('Copy')->fill(['name' => 'name'])];
    }

    public static function isCreatableInAdmin(): bool
    {
        return false;
    }

    public static function isEditableInAdmin(): bool
    {
        return false;
    }
}
