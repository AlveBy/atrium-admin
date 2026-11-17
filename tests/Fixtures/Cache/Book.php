<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cache;

use Atrium\Admin\Cache\CachesRecords;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Contracts\Cacheable;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\BelongsToManyField;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\GalleryField;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\HasAdminSeo;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A record with a translated name, a "brand", tags, pictures and SEO: what
 * a product card of a catalog carries.
 */
final class Book extends Model implements Administrable, Cacheable
{
    use CachesRecords, HasAdminSeo, InteractsWithAdmin, Translatable;

    protected $table = 'cache_books';

    protected $fillable = ['name', 'active', 'price', 'shelf_id', 'cover', 'photos'];

    /** @var array<int,string> */
    public array $translatable = ['name'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'price' => 'integer', 'photos' => 'array'];
    }

    /**
     * @return BelongsTo<Shelf,$this>
     */
    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class, 'shelf_id');
    }

    /**
     * @return BelongsToMany<Tag,$this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'cache_book_tag', 'book_id', 'tag_id');
    }

    public static function getAdminLabel(): string
    {
        return 'Books';
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'books';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            CheckboxField::make('active')->label('Active')->default(true),
            NumberField::make('price')->label('Price'),
            BelongsToField::make('shelf_id')->label('Shelf')->model(Shelf::class),
            BelongsToManyField::make('tags')->label('Tags')->model(Tag::class),
            ImageField::make('cover')->label('Cover'),
            GalleryField::make('photos')->label('Photos'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }
}
