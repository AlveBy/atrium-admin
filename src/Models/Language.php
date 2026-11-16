<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Atrium\Admin\Columns\BooleanColumn;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sitemap;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A language the site's content is written in — not the panel's own
 * interface language (that is config('atrium.locales')).
 *
 * The code is the key of every translation stored in a translatable column
 * and the prefix of the public addresses (/de/…), so it is fixed once the
 * language exists.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $active
 * @property bool $is_default
 * @property int $sort
 */
class Language extends Model implements Administrable
{
    use InteractsWithAdmin;

    public const CODE_PATTERN = '[a-z]{2,3}(-[a-z0-9]{2,8})?';

    protected $table = 'admin_languages';

    protected $fillable = ['code', 'name', 'active', 'is_default', 'sort'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_default' => 'boolean',
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Language $language): void {
            // There is always exactly one default language, and it is shown.
            $others = static::query()->where('is_default', true)->whereKeyNot($language->getKey());

            if (! $language->is_default && ! $others->exists()) {
                $language->is_default = true;
            }

            if ($language->is_default) {
                $language->active = true;
            }

            if ($language->exists && $language->isDirty('code')) {
                $language->code = (string) $language->getOriginal('code');
            }
        });

        static::saved(function (Language $language): void {
            if ($language->is_default) {
                static::query()->where('is_default', true)->whereKeyNot($language->getKey())->update(['is_default' => false]);
            }

            app(ContentLanguages::class)->flush();
            app(Sitemap::class)->flush();
        });

        static::deleted(static function (): void {
            app(ContentLanguages::class)->flush();
            app(Sitemap::class)->flush();
        });
    }

    public static function getAdminLabel(): string
    {
        return __('atrium::menu.languages');
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'languages';
    }

    public static function getAdminCreateLabel(): string
    {
        return __('atrium::languages.create');
    }

    public static function getDefaultSortColumn(): string
    {
        return 'sort';
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')
                ->label(__('atrium::languages.fields.name'))
                ->hint(__('atrium::languages.hints.name'))
                ->required()
                ->maxLength(255),

            TextField::make('code')
                ->label(__('atrium::languages.fields.code'))
                ->hint(__('atrium::languages.hints.code'))
                ->required()
                ->rules(['string', 'max:12', 'regex:/^'.self::CODE_PATTERN.'$/'])
                ->unique('admin_languages', 'code')
                ->onlyOnCreate(),

            TextField::make('code')
                ->label(__('atrium::languages.fields.code'))
                ->hint(__('atrium::languages.hints.code_fixed'))
                ->readonly()
                ->onlyOnEdit(),

            CheckboxField::make('active')
                ->label(__('atrium::languages.fields.active'))
                ->default(true),

            CheckboxField::make('is_default')
                ->label(__('atrium::languages.fields.is_default'))
                ->hint(__('atrium::languages.hints.is_default'))
                ->default(false),

            NumberField::make('sort')
                ->label(__('atrium::languages.fields.sort'))
                ->default(500),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('name')->label(__('atrium::languages.fields.name'))->sortable()->searchable(),
            TextColumn::make('code')->label(__('atrium::languages.fields.code'))->sortable()->searchable(),
            BooleanColumn::make('active')->label(__('atrium::languages.fields.active')),
            BooleanColumn::make('is_default')->label(__('atrium::languages.fields.is_default')),
            TextColumn::make('sort')->label(__('atrium::languages.fields.sort'))->sortable(),
        ];
    }

    /**
     * The default language holds the text every other language falls back to.
     */
    public function canDelete(): bool
    {
        return ! $this->is_default;
    }

    /**
     * The value for hreflang: "pt-br" → "pt-BR".
     */
    public function hreflang(): string
    {
        $parts = explode('-', $this->code, 2);

        return isset($parts[1]) ? $parts[0].'-'.strtoupper($parts[1]) : $parts[0];
    }
}
