<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Actions\CreateFromRecord;
use Atrium\Admin\Actions\LinkAction;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Tests\Fixtures\Actions\Flag;
use Atrium\Admin\Tests\Fixtures\Actions\Inspect;
use Atrium\Admin\Tests\Fixtures\Actions\Publish;
use Atrium\Admin\Tests\Fixtures\Actions\QueuedFlag;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with every kind of button: links, server actions, a filled
 * create form and bulk actions, one of them queued. "Locked" may not be
 * deleted, "Pinned" refuses when it comes to it.
 */
final class Dispatch extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'posts';

    protected $fillable = ['name', 'active', 'sort'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort' => 'integer'];
    }

    public static function getAdminLabel(): string
    {
        return 'Dispatches';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name')->required(), NumberField::make('sort')->label('Sort')];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }

    public static function getAdministrationActions(): array
    {
        return [
            LinkAction::make('site')->label('Elsewhere')->url('https://example.org/'),
            LinkAction::make('preview')->label('Preview')->url(fn (Dispatch $dispatch): string => '/preview/'.$dispatch->id)->newTab(),
            Publish::make('publish')->label('Publish')->permission('publish')->confirm('Publish it?'),
            Inspect::make('inspect')->label('Inspect')->permission('view'),
            CreateFromRecord::make('copy')->label('Copy')->fill(['name' => 'name']),
            'not an action',
        ];
    }

    public static function getAdministrationBulkActions(): array
    {
        return [
            Flag::make('flag')->label('Hide'),
            QueuedFlag::make('queued')->label('Hide later'),
            'not an action',
        ];
    }

    public function canDelete(): bool
    {
        return $this->name !== 'Locked';
    }

    public function beforeAdminDelete(): bool
    {
        return $this->name !== 'Pinned';
    }
}
