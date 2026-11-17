<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cards;

use Atrium\Admin\Contracts\Cacheable;
use Atrium\Admin\Fields\FileField;
use Atrium\Admin\Fields\GalleryField;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\HasAdminSeo;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An item with every kind of relation and field a card builder meets. What
 * the model declares for the cache (relations, form fields) is set by the
 * test: `Item::declare()`, and gone with `Item::forget()`.
 */
final class Item extends Model implements Cacheable
{
    use HasAdminSeo, Translatable;

    protected $table = 'card_items';

    protected $guarded = [];

    public $timestamps = false;

    protected $attributes = ['subject_type' => Section::class];

    /** @var array<int,string> */
    public array $translatable = ['name', 'summary'];

    /** @var array<int|string,string> */
    private static array $relationNames = ['owner', 'tags'];

    /** @var array<int,mixed>|null */
    private static ?array $fields = null;

    public static int $relationNameCalls = 0;

    public static int $fieldCalls = 0;

    /**
     * @param  array<int|string,string>|null  $relations
     * @param  array<int,mixed>|null  $fields
     */
    public static function declare(?array $relations = null, ?array $fields = null): void
    {
        self::$relationNames = $relations ?? self::$relationNames;
        self::$fields = $fields;
    }

    public static function forget(): void
    {
        self::$relationNames = ['owner', 'tags'];
        self::$fields = null;
        self::$relationNameCalls = 0;
        self::$fieldCalls = 0;
    }

    /**
     * @return array<int|string,string>
     */
    public function cacheRelations(): array
    {
        self::$relationNameCalls++;

        return self::$relationNames;
    }

    /**
     * @return array<int,mixed>
     */
    public static function getAdministrationFields(): array
    {
        self::$fieldCalls++;

        return self::$fields ?? [
            TextField::make('name'),
            ImageField::make('picture'),
            GalleryField::make('files'),
            FileField::make('docs'),
        ];
    }

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return ['docs' => 'array'];
    }

    /**
     * @return BelongsTo<Owner,$this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }

    /**
     * @return BelongsTo<HalfTranslated,$this>
     */
    public function halfOwner(): BelongsTo
    {
        return $this->belongsTo(HalfTranslated::class, 'owner_id');
    }

    /**
     * @return BelongsTo<self,$this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(self::class, 'link_id');
    }

    /**
     * @return BelongsTo<Section,$this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    /**
     * @return BelongsToMany<Label,$this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'card_item_label', 'item_id', 'label_id');
    }

    /**
     * @return BelongsToMany<self,$this>
     */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'card_item_related', 'item_id', 'related_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function broken(): never
    {
        throw new \RuntimeException('no such relation');
    }

    public function notARelation(): string
    {
        return 'text';
    }
}
