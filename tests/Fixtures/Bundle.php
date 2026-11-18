<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\BelongsToManyField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A module with two many-to-many fields that have a relation behind them and
 * one between them that has none, after a plain field.
 */
final class Bundle extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'bundles';

    protected $fillable = ['name'];

    public static function getAdminLabel(): string
    {
        return 'Bundles';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            BelongsToManyField::make('extras')->label('Extras')->model(Post::class),
            BelongsToManyField::make('posts')->label('Posts')->model(Post::class),
            BelongsToManyField::make('topics')->label('Topics')->model(Topic::class),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }

    /**
     * @return BelongsToMany<Post,$this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'bundle_post');
    }

    /**
     * @return BelongsToMany<Topic,$this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'bundle_topic');
    }
}
