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
 * A record with an SEO tab that writes a second row when it is saved and
 * refuses to be deleted while it is pinned. Kept in the posts table.
 */
final class Article extends Model implements Administrable
{
    use HasAdminSeo, InteractsWithAdmin;

    protected $table = 'posts';

    protected $fillable = ['name'];

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
        return [TextColumn::make('name')->label('Name')];
    }

    public function afterAdminSave(array $data): void
    {
        Post::query()->create(['name' => "Index of {$this->name}"]);
    }

    public function beforeAdminDelete(): bool
    {
        return $this->name !== 'Pinned';
    }
}
