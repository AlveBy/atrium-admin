<?php

declare(strict_types=1);

namespace Atrium\Admin\Modules;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Resources\ResourceRegistry;
use Closure;

/**
 * Soft links between modules: a module that works without another one and
 * links to it when it is there. Products work without brands; the "Brand"
 * field and column appear only when the Brands module is installed:
 *
 *     ...Soft::when(Brand::class, fn () => [
 *         BelongsToField::make('brand_id')->label('Brand')->model(Brand::class),
 *     ]),
 *
 * The same goes for list columns, tabs and anything else built from a list.
 * Relations are soft with Atrium\Admin\Traits\HasSoftRelations. Naming
 * `Brand::class` does not load the class, so it may be missing altogether.
 */
final class Soft
{
    /**
     * Whether a model can be used: its class exists and its module, if a
     * module brings it, is installed.
     */
    public static function available(string $model): bool
    {
        return app(Modules::class)->available($model);
    }

    /**
     * The items when every one of these models is available, otherwise
     * nothing. Spread the result into a list of fields or columns.
     *
     * @template T
     *
     * @param  string|array<int,string>  $models
     * @param  array<int|string,T>|Closure():array<int|string,T>  $items
     * @return array<int|string,T>
     */
    public static function when(string|array $models, array|Closure $items): array
    {
        foreach ((array) $models as $model) {
            if (! self::available($model)) {
                return [];
            }
        }

        return $items instanceof Closure ? $items() : $items;
    }

    /**
     * Every content module a record can be attached to (reviews, comments,
     * …): model class => label, for a select of the type column of a soft
     * morphTo relation. The panel's sites and languages are not content.
     *
     * @return array<class-string,string>
     */
    public static function targets(): array
    {
        $targets = [];

        foreach (app(ResourceRegistry::class)->all() as $model) {
            if (! in_array($model, [Site::class, Language::class], true)) {
                $targets[$model] = $model::getAdminLabel();
            }
        }

        return $targets;
    }
}
