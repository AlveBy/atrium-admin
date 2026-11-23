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
use Atrium\Admin\Fields\BelongsToManyField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A module whose relation fields search their records: posts (active ones
 * only), notes of the site, translated pages and topics, which are no module.
 */
final class Kit extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'kits';

    protected $fillable = ['name', 'post_id'];

    public static function getAdminLabel(): string
    {
        return 'Kits';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            BelongsToField::make('post_id')->label('Main post')->model(Post::class)->searchThreshold(3),
            BelongsToManyField::make('posts')->label('Posts')->model(Post::class)->searchThreshold(3)->maxItems(5)
                ->query(fn (Builder $query): Builder => $query->where('active', true)),
            BelongsToManyField::make('notes')->label('Notes')->model(Note::class)->searchThreshold(0),
            BelongsToManyField::make('pages')->label('Pages')->model(Page::class)->searchThreshold(0),
            BelongsToManyField::make('topics')->label('Topics')->model(Topic::class)->searchThreshold(0),
            BelongsToManyField::make('loose')->label('No model'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')->searchable()];
    }

    /**
     * @return BelongsToMany<Post,$this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'kit_post')->withPivot('sort')->orderByPivot('sort');
    }

    /**
     * @return BelongsToMany<Note,$this>
     */
    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class, 'kit_note');
    }

    /**
     * @return BelongsToMany<Page,$this>
     */
    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'kit_page');
    }

    /**
     * @return BelongsToMany<Topic,$this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'kit_topic');
    }
}
