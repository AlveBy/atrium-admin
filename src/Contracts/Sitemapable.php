<?php

declare(strict_types=1);

namespace Atrium\Admin\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * A model whose records have public pages: the core lists them in
 * sitemap.xml of the site they belong to, in every language they exist in.
 *
 *     public static function sitemapQuery(): Builder
 *     {
 *         return static::query()->where('active', true);
 *     }
 *
 *     public function sitemapUrl(string $language): ?string
 *     {
 *         $code = $this->getTranslation('code', $language, false);
 *
 *         return $code ? app(ContentLanguages::class)->route('articles.show', ['code' => $code], $language) : null;
 *     }
 *
 * Records of a model that uses BelongsToSite are listed only in their own
 * site's sitemap. The page's last change is taken from updated_at.
 */
interface Sitemapable
{
    /**
     * The records that are public: active, published, not hidden.
     *
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public static function sitemapQuery(): Builder;

    /**
     * The absolute address of the record's page in a content language, or
     * null if the record has no page in that language.
     */
    public function sitemapUrl(string $language): ?string;
}
