<?php

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Scopes\SiteScope;
use Atrium\Admin\Support\Sites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records of this module belong to one site (a `site_id` column):
 *
 *     use BelongsToSite;
 *
 *     $table->foreignId('site_id')->nullable()->index();  // in the migration
 *
 * The admin lists the records of the site picked in its header, the public
 * pages of a domain see only that site's records, and a new record goes to
 * the current site. A unique field (a page code) is unique within a site.
 * Modules without the trait are shared by all sites.
 */
trait BelongsToSite
{
    public static function bootBelongsToSite(): void
    {
        static::addGlobalScope(new SiteScope);

        static::creating(static function (Model $model): void {
            if (empty($model->getAttribute('site_id'))) {
                $model->setAttribute('site_id', app(Sites::class)->current()->id);
            }
        });
    }

    /**
     * @return BelongsTo<Site,$this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
