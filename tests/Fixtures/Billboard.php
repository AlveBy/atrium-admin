<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Contracts\Previewable;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\LayoutField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Closure;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with a page on the site that the page editor can draw, with layout
 * fields the form does not let change, and with switches the tests flip.
 */
final class Billboard extends Model implements Administrable, Previewable
{
    use InteractsWithAdmin;

    /** @var (Closure(self,string):string)|null what the page looks like */
    public static ?Closure $draw = null;

    public static bool $layoutRequired = false;

    public static bool $editable = true;

    /** @var array<int,bool|string|null> the lock of each query of the module */
    public static array $locks = [];

    protected $table = 'billboards';

    protected $fillable = ['name', 'active', 'layout', 'aside', 'frozen', 'tucked'];

    /**
     * Puts the switches back for the next test.
     */
    public static function reset(): void
    {
        self::$draw = null;
        self::$layoutRequired = false;
        self::$editable = true;
        self::$locks = [];
    }

    protected static function booted(): void
    {
        self::addGlobalScope('locks', static function (Builder $query): void {
            self::$locks[] = $query->getQuery()->lock;
        });
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'layout' => 'array', 'aside' => 'array', 'frozen' => 'array', 'tucked' => 'array'];
    }

    public static function getAdminLabel(): string
    {
        return 'Billboards';
    }

    public static function isEditableInAdmin(): bool
    {
        return self::$editable;
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Title')->required(),
            CheckboxField::make('active')->label('Active')->default(true),
            LayoutField::make('layout')->label('Blocks')->required(self::$layoutRequired),
            LayoutField::make('aside')->label('Aside')->readonly(),
            LayoutField::make('frozen')->label('Frozen')->disabled(),
            LayoutField::make('tucked')->label('Tucked away')->hideOnEdit(),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Title')];
    }

    public function adminPreview(string $language): Renderable
    {
        $html = (self::$draw ?? static fn (self $billboard, string $language): string => '<html><head></head><body>'.e((string) json_encode($billboard->layout)).'</body></html>')($this, $language);

        return new class($html) implements Renderable
        {
            public function __construct(private readonly string $html) {}

            public function render(): string
            {
                return $this->html;
            }
        };
    }
}
