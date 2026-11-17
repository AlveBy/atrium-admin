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
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\ColorField;
use Atrium\Admin\Fields\ContentField;
use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Fields\FileField;
use Atrium\Admin\Fields\GalleryField;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\MapField;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\RepeatableField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\SlugField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\TreeSelectField;
use Atrium\Admin\Fields\WysiwygField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A module with one field of every kind, for the API documentation: what an
 * outside program is told to send for each.
 */
final class ApiShowcase extends Model implements Administrable
{
    use InteractsWithAdmin, Translatable;

    protected $table = 'api_showcases';

    protected $fillable = ['name', 'code', 'notes', 'price', 'post_id', 'aisle_id'];

    /** @var array<int,string> */
    protected array $translatable = ['name'];

    /**
     * @return BelongsToMany<Post,$this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'api_showcase_post');
    }

    public static function getAdminLabel(): string
    {
        return 'Showcase | of <b>fields</b>';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required()->maxLength(120),
            TextField::make('code')->label('Code')->hint('Unique | per site')->onlyOnCreate(),
            TextareaField::make('notes')->label('Notes')->onlyOnEdit(),
            TextField::make('secret')->label('Never in a form')->hideOnCreate()->hideOnEdit(),
            NumberField::make('price')->label('Price'),
            CheckboxField::make('active')->label('Active'),
            CheckboxField::make('booking')->label('Booking')->checkedValue('YES'),
            DateField::make('starts_on')->label('Starts on'),
            DateTimeField::make('starts_at')->label('Starts at'),
            SlugField::make('slug')->label('Slug'),
            ContentField::make('body')->label('Body'),
            WysiwygField::make('intro')->label('Intro'),
            ColorField::make('colour')->label('Colour'),
            MapField::make('location')->label('Location'),
            ImageField::make('picture')->label('Picture'),
            FileField::make('leaflet')->label('Leaflet'),
            GalleryField::make('photos')->label('Photos'),
            RepeatableField::make('dates')->label('Dates')->itemType('date'),
            RepeatableField::make('times')->label('Times')->itemType('datetime'),
            RepeatableField::make('amounts')->label('Amounts')->itemType('number'),
            RepeatableField::make('fragments')->label('Fragments')->itemType('content'),
            RepeatableField::make('words')->label('Words'),
            SelectField::make('kind')->label('Kind')->options(['tour' => 'Tour', 'cruise' => 'Cruise | sea']),
            SelectField::make('mood')->label('Mood')->options([]),
            MultiSelectField::make('extras')->label('Extras')->options(['wifi' => 'Wi-Fi', 'pool' => 'Pool']),
            TreeSelectField::make('aisle_id')->label('Aisle')->model(ApiAisle::class),
            TreeSelectField::make('aisles')->label('Aisles')->model(ApiAisle::class)->multiple(),
            BelongsToField::make('post_id')->label('Post')->model(Post::class),
            BelongsToField::make('owner_id')->label('Owner')->model(ApiAisle::class),
            BelongsToManyField::make('posts')->label('Posts')->model(Post::class),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }
}
