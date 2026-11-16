<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\BelongsToSite;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A module whose records belong to one site each.
 */
final class Note extends Model implements Administrable
{
    use BelongsToSite, InteractsWithAdmin;

    protected $table = 'notes';

    protected $fillable = ['name', 'code', 'site_id'];

    public static function getAdminLabel(): string
    {
        return 'Notes';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Title')->required(),
            TextField::make('code')->label('Code')->required()->unique('notes', 'code'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('name')->label('Title')->searchable(),
        ];
    }
}
