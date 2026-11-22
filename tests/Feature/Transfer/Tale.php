<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A module whose name and code are kept per content language, and whose
 * records are found by the code: what an import does with a key that has a
 * column for each language. It lives in the stories table, which is
 * translatable.
 */
final class Tale extends Model implements Administrable
{
    use InteractsWithAdmin, Translatable;

    protected $table = 'stories';

    protected $guarded = [];

    /** @var array<int,string> */
    protected array $translatable = ['name', 'code'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * @return array<int,string>
     */
    public static function getImportKeys(): array
    {
        return ['code', 'id'];
    }

    public static function getAdminLabel(): string
    {
        return 'Tales';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Title')->required(),
            TextField::make('code')->label('Code'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Title')];
    }
}
