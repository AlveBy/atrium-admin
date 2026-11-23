<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\HasAdminTabs;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with tabs whose list leaves drafts out: its relation field is
 * searched on a tabbed form, and searches of it skip what its list skips.
 */
final class Crate extends Model implements Administrable
{
    use HasAdminTabs, InteractsWithAdmin;

    protected $table = 'kits';

    protected $fillable = ['name', 'post_id'];

    public static function getAdminLabel(): string
    {
        return 'Crates';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            BelongsToField::make('post_id')->label('Post')->model(Post::class)->searchThreshold(0),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }

    public function scopeAdminList(Builder $query): Builder
    {
        return $query->where('name', 'not like', 'Draft%');
    }
}
