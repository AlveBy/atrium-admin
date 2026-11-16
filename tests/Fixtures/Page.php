<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\SlugField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

final class Page extends Model implements Administrable
{
    use InteractsWithAdmin, Translatable;

    protected $table = 'pages';

    protected $fillable = ['name', 'code', 'sort'];

    /** @var array<int,string> */
    protected array $translatable = ['name', 'code'];

    public static function getAdminLabel(): string
    {
        return 'Pages';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Title')->required(),
            SlugField::make('code')->label('Code')->from('name')->unique('pages', 'code'),
            TextField::make('sort')->label('Sort'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('name')->label('Title')->sortable()->searchable(),
        ];
    }
}
