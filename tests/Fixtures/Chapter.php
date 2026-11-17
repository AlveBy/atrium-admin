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
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\HasAdminTabs;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A module with a button that opens a subchapter's create form, a section
 * to preselect and fields on tabs.
 */
final class Chapter extends Model implements Administrable
{
    use HasAdminTabs, InteractsWithAdmin;

    protected $table = 'chapters';

    protected $fillable = ['name', 'parent_id', 'section_id', 'kind'];

    public static function getAdminLabel(): string
    {
        return 'Chapters';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            NumberField::make('parent_id')->label('Parent'),
            NumberField::make('section_id')->label('Section'),
            TextField::make('kind')->label('Kind'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }

    public static function getAdministrationActions(): array
    {
        return [
            CreateFromRecord::make('subchapter')->label('Subchapter')->fill(['parent_id' => 'id']),
            'not an action',
            CreateFromRecord::make('sibling')->label('Same kind')->fill(['kind' => 'kind']),
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'section_id');
    }
}
