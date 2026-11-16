<?php

declare(strict_types=1);

namespace Atrium\Admin\Demo;

use Atrium\Admin\Demo\Models\Brand;
use Atrium\Admin\Demo\Models\CatalogSection;
use Atrium\Admin\Demo\Models\News;
use Atrium\Admin\Demo\Models\Product;
use Atrium\Admin\Demo\Models\Store;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Support\PublicUrl;
use Illuminate\Contracts\Foundation\Application;

/**
 * The demo modules: a catalog shaped like a migrated Bitrix iblock, brands,
 * translated news of two sites and stores, between them every field type of
 * the panel. They exist only with `atrium.demo` on (`atrium:install --demo`
 * turns it on and fills them); off again, they leave the menu and the site.
 */
final class Demo
{
    /** In menu order. */
    public const MODELS = [Product::class, CatalogSection::class, Brand::class, News::class, Store::class];

    public static function enabled(): bool
    {
        return (bool) config('atrium.demo', false);
    }

    public static function migrations(): string
    {
        return dirname(__DIR__, 2).'/database/demo';
    }

    /**
     * Pages of the demo modules on the site; brands are a directory and
     * have none. A rule the app sets for the same model wins.
     *
     * @return array<class-string,array<string,string>>
     */
    public static function publicUrls(): array
    {
        return [
            Product::class => ['prefix' => 'catalog', 'tree' => CatalogSection::class, 'parent' => 'section_id'],
            CatalogSection::class => ['prefix' => 'catalog', 'tree' => CatalogSection::class, 'parent' => 'parent_id'],
            News::class => ['prefix' => 'news'],
            Store::class => ['prefix' => 'stores'],
        ];
    }

    /**
     * Wire the modules in: menu, migrations, public addresses. Called while
     * booting when the flag is on, and by `atrium:install --demo` when it
     * has just been turned on.
     */
    public static function register(Application $app): void
    {
        $config = $app['config'];
        $config->set('atrium.demo', true);
        $config->set('atrium.public_urls', (array) $config->get('atrium.public_urls', []) + self::publicUrls());

        $app->afterResolving('migrator', static fn ($migrator) => $migrator->path(self::migrations()));

        if ($app->resolved('migrator')) {
            $app->make('migrator')->path(self::migrations());
        }

        if ($app->resolved(ResourceRegistry::class)) {
            $app->make(ResourceRegistry::class)->flush();
        }

        PublicUrl::forgetPaths();
    }
}
