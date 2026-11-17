<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cache;

use Atrium\Admin\Cache\CachesRecords;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Contracts\Cacheable;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\TreeSelectField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * A form whose fields point at relations in every way the cache has to
 * judge: a tree select, a duplicate, a name with no "_id", a relation to its
 * own class, a method that is no relation, one that fails, one that is
 * missing, a name a query builder answers to. Only its form is read.
 */
final class Chapter extends Model implements Administrable, Cacheable
{
    use CachesRecords, InteractsWithAdmin;

    protected $table = 'cache_books';

    /**
     * @return BelongsTo<Shelf,$this>
     */
    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class, 'shelf_id');
    }

    /**
     * @return BelongsTo<Shelf,$this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Shelf::class, 'section_id');
    }

    /**
     * @return BelongsTo<Shelf,$this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Shelf::class, 'owner');
    }

    /**
     * @return BelongsTo<Chapter,$this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'chapter_id');
    }

    /**
     * Not a relation, though it answers like one.
     */
    public function fake(): object
    {
        return new class
        {
            public function getRelated(): Shelf
            {
                return new Shelf;
            }
        };
    }

    public function broken(): BelongsTo
    {
        throw new RuntimeException('No such relation.');
    }

    public static function getAdminLabel(): string
    {
        return 'Chapters';
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'chapters';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name'),
            BelongsToField::make('shelf_id')->label('Shelf')->model(Shelf::class),
            BelongsToField::make('shelf_id')->label('Shelf again')->model(Shelf::class),
            TreeSelectField::make('section_id')->label('Section'),
            BelongsToField::make('owner')->label('Owner')->model(Shelf::class),
            BelongsToField::make('chapter_id')->label('Chapter')->model(self::class),
            BelongsToField::make('fake_id')->label('Fake')->model(Shelf::class),
            BelongsToField::make('broken_id')->label('Broken')->model(Shelf::class),
            BelongsToField::make('ghost_id')->label('Ghost')->model(Shelf::class),
            BelongsToField::make('count_id')->label('Count')->model(Shelf::class),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [];
    }
}
