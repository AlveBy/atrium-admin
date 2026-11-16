<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Contracts\Sitemapable;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Traits\BelongsToSite;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with public pages: per site, translatable, in sitemap.xml.
 */
final class Story extends Model implements Administrable, Sitemapable
{
    use BelongsToSite, InteractsWithAdmin, Translatable;

    protected $table = 'stories';

    protected $fillable = ['name', 'code', 'active', 'site_id'];

    /** @var array<int,string> */
    protected array $translatable = ['name', 'code'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public static function getAdminLabel(): string
    {
        return 'Stories';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Title')->required()];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Title')];
    }

    public static function sitemapQuery(): Builder
    {
        return self::query()->where('active', true);
    }

    public function sitemapUrl(string $language): ?string
    {
        $code = $this->getTranslation('code', $language, false);

        return $code ? app(ContentLanguages::class)->route('stories.show', ['code' => $code], $language) : null;
    }
}
