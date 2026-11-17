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
use Atrium\Admin\Traits\HasAdminSeo;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with an SEO tab whose records may refuse to be deleted: "Locked"
 * by canDelete(), "Kept" by its delete hook.
 *
 * @property int $id
 * @property string $name
 */
final class ApiArticle extends Model implements Administrable
{
    use HasAdminSeo, InteractsWithAdmin;

    protected $table = 'posts';

    protected $fillable = ['name', 'active', 'sort'];

    public static function getAdminLabel(): string
    {
        return 'Articles';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name')->required()];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')->searchable()];
    }

    public function canDelete(): bool
    {
        return $this->name !== 'Locked';
    }

    public function beforeAdminDelete(): bool
    {
        return $this->name !== 'Kept';
    }
}
