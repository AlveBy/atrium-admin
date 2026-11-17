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
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A module with hand-written validation rules in every unique:… form, a field
 * with no label, a many-to-many field with no relation behind it and a list
 * column from a translated related record.
 */
final class Topic extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'topics';

    protected $fillable = ['name', 'code', 'slug', 'note', 'page_id'];

    public static function getAdminLabel(): string
    {
        return 'Topics';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name'),
            TextField::make('code')->label('Code'),
            TextField::make('slug')->label('Slug'),
            TextField::make('note')->label(''),
            NumberField::make('page_id')->label('Page'),
            BelongsToManyField::make('related_posts')->label('Related')->model(Post::class),
        ];
    }

    public static function getCreateValidationRules(): array
    {
        return [
            'name' => 'required|unique:topics',
            'code' => ['nullable', 'unique:topics,NULL'],
            'slug' => 'nullable|unique:topics,slug,0',
            'note' => 'nullable|max:5',
            'related_posts' => 'nullable|array',
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('name')->label('Name'),
            TextColumn::make('page.name')->label('Page'),
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
