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
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Fields\GalleryField;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\RepeatableField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\TreeSelectField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with a field of every kind an import has to read from a cell of
 * text: numbers, yes/no, dates, choices, lists, pictures, a tree, a link to
 * another module, a name per language.
 *
 * @property int $id
 * @property string|null $article
 * @property string|null $code
 */
final class Parcel extends Model implements Administrable
{
    use InteractsWithAdmin, Translatable;

    protected $table = 'parcels';

    protected $guarded = [];

    /** @var array<int,string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'starts_on' => 'date',
            'colors' => 'array',
            'features' => 'array',
            'photos' => 'array',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function getImportKeys(): array
    {
        return ['article', 'code', 'id'];
    }

    /** No "Publish at": its export is about the cells of its own fields. */
    public static function usesAdminSchedule(): bool
    {
        return false;
    }

    public static function getAdminLabel(): string
    {
        return 'Parcels';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('article')->label('Article')->maxLength(40),
            TextField::make('name')->label('Name')->required()->maxLength(120),
            TextField::make('code')->label('Code')->maxLength(120),
            NumberField::make('price')->label('Price'),
            CheckboxField::make('active')->label('Active')->default(true),
            CheckboxField::make('booking')->label('Booking')->checkedValue('YES'),
            DateTimeField::make('starts_at')->label('Starts at'),
            DateField::make('starts_on')->label('Starts on'),
            SelectField::make('label')->label('Label')->options(['hit' => 'Bestseller', 'new' => 'New']),
            MultiSelectField::make('colors')->label('Colours')->options(['black' => 'Black', 'silver' => 'Silver']),
            RepeatableField::make('features')->label('Features'),
            ImageField::make('picture')->label('Picture'),
            GalleryField::make('photos')->label('Photos'),
            TreeSelectField::make('aisle_id')->label('Aisle')->model(ApiAisle::class),
            BelongsToField::make('post_id')->label('Post')->model(Post::class),
            TextareaField::make('detail_text')->label('Description'),
            NumberField::make('sort')->label('Sort')->default(500),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')->searchable()->sortable(), TextColumn::make('article')->label('Article')];
    }
}
