<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Auth\SignIn;
use Atrium\Admin\Cache\CacheStore;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Cache\Versions;
use Atrium\Admin\Captcha\CaptchaManager;
use Atrium\Admin\Contracts\Sitemapable;
use Atrium\Admin\Hooks\Hooks;
use Atrium\Admin\Http\Middleware\AdminAuthenticate;
use Atrium\Admin\Http\Middleware\AuthenticateApiToken;
use Atrium\Admin\Http\Middleware\AuthorizeAdminRoute;
use Atrium\Admin\Http\Middleware\CacheDebug;
use Atrium\Admin\Http\Middleware\CachePages;
use Atrium\Admin\Http\Middleware\EnterSiteFolder;
use Atrium\Admin\Http\Middleware\HandleAtriumRequests;
use Atrium\Admin\Http\Middleware\RecordPageTiming;
use Atrium\Admin\Http\Middleware\RedirectIfAdminAuthenticated;
use Atrium\Admin\Http\Middleware\ResolveSite;
use Atrium\Admin\Http\Middleware\SetContentLocale;
use Atrium\Admin\Http\Middleware\SlugRedirectMiddleware;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Modules\Settings;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Services\SlugService;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Locales;
use Atrium\Admin\Support\Menu;
use Atrium\Admin\Support\Sitemap;
use Atrium\Admin\Support\Sites;
use Closure;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

final class AtriumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atrium.php', 'atrium');

        $config = $this->app['config'];

        // A guard and a user table of its own: administrators are not the
        // application's users and never share a session with them.
        $config->set('auth.guards.admin', [
            'driver' => 'session',
            'provider' => 'admin_users',
        ]);

        $config->set('auth.providers.admin_users', [
            'driver' => 'eloquent',
            'model' => $config->get('atrium.auth.model', AdminUser::class),
        ]);

        $config->set('auth.passwords.admin_users', [
            'provider' => 'admin_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ]);

        // The panel's JS only needs its own routes, not the whole application's.
        $config->set('ziggy.groups.atrium', ['admin.*']);

        $this->app->singleton(SlugService::class);
        $this->app->singleton(Modules::class);
        $this->app->singleton(Settings::class);
        $this->app->singleton(Hooks::class);
        $this->app->singleton(CaptchaManager::class);
        $this->app->singleton(ResourceRegistry::class);
        $this->app->singleton(ApiResources::class);
        $this->app->singleton(Permissions::class);
        $this->app->singleton(Menu::class);
        $this->app->singleton(Locales::class);
        $this->app->singleton(ContentLanguages::class);
        $this->app->singleton(Sites::class);
        $this->app->singleton(Sitemap::class);
        $this->app->singleton(SignIn::class);
        $this->app->afterResolving(SignIn::class, function (SignIn $signIn): void {
            // How an administrator is let in once every sign-in step is passed.
            $signIn->finishUsing((string) config('atrium.auth.guard', 'admin'), function ($request, $user, bool $remember) {
                // The session guard renews the session id and the CSRF token itself.
                Auth::guard((string) config('atrium.auth.guard', 'admin'))->login($user, $remember);

                return redirect()->intended(route(config('atrium.auth.redirect_after_login', 'admin.dashboard')));
            });
        });
        $this->app->singleton(CacheStore::class, fn (): CacheStore => CacheStore::fromConfig());
        $this->app->singleton(Versions::class, fn ($app): Versions => new Versions($app->make(CacheStore::class)->repository()));
        $this->app->singleton(RecordCache::class);
    }

    public function boot(): void
    {
        $this->registerInertiaPages();
        $this->registerMiddleware();
        $this->registerResources();
        $this->registerRoutes();
        $this->registerLocalizedRoutes();
        $this->registerSitemapFlush();
        $this->registerCache();
        $this->registerCommands();
        $this->registerCdnAssets();
    }

    /**
     * Built scripts and styles from the CDN too, when asked for
     * (ATRIUM_CDN_ASSETS): the CDN must then send CORS headers, browsers load
     * Vite's module scripts and fonts across origins only with them.
     */
    private function registerCdnAssets(): void
    {
        if (config('atrium.cdn.assets') && Support\Cdn::enabled()) {
            Vite::createAssetPathsUsing(Support\Cdn::asset(...));
        }
    }

    /**
     * Let Inertia's page assertions find the panel's pages as well. In boot():
     * Inertia merges its own config in register(), and setting a nested key
     * before that would replace its whole `pages` section.
     */
    private function registerInertiaPages(): void
    {
        $config = $this->app['config'];
        $pages = (string) realpath(__DIR__.'/../resources/js/Pages');

        foreach (['inertia.pages.paths', 'inertia.testing.page_paths'] as $key) {
            $paths = (array) $config->get($key, [resource_path('js/Pages')]);
            $config->set($key, array_values(array_unique([...$paths, $pages])));
        }
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->aliasMiddleware('admin.auth', AdminAuthenticate::class);
        $router->aliasMiddleware('admin.can', AuthorizeAdminRoute::class);
        $router->aliasMiddleware('admin.guest', RedirectIfAdminAuthenticated::class);
        $router->aliasMiddleware('admin.api', AuthenticateApiToken::class);
        $router->aliasMiddleware('atrium.inertia', HandleAtriumRequests::class);
        $router->aliasMiddleware('slug.redirect', SlugRedirectMiddleware::class);
        $router->aliasMiddleware('atrium.content-locale', SetContentLocale::class);
        $router->aliasMiddleware('atrium.site', ResolveSite::class);

        // Every page of the app knows its site, picked by domain and folder.
        // The folder goes before routing, so the kernel's global stack.
        $this->app->make(HttpKernel::class)->pushMiddleware(EnterSiteFolder::class);
        $this->appendToWebGroup(ResolveSite::class);

        // The full-page cache (off by default) wraps the page once the site
        // is known; the debug line goes around everything to count hits too.
        // Both check their settings on every request and step aside.
        $this->appendToWebGroup(CachePages::class);
        $this->app->make(HttpKernel::class)->pushMiddleware(CacheDebug::class);

        // Page timings feed the dashboard gadgets. Added here so the gadgets
        // work right after install, without editing bootstrap/app.php.
        if (config('atrium.dashboard.timings.enabled', true)) {
            $this->appendToWebGroup(RecordPageTiming::class);
        }
    }

    /**
     * Through the HTTP kernel, not the router: the kernel copies its own
     * groups over the router's whenever anyone changes its middleware
     * (Sanctum does, while booting), and a group pushed to the router alone
     * silently disappears.
     */
    private function appendToWebGroup(string $middleware): void
    {
        $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', $middleware);
    }

    private function registerResources(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $modules = $this->app->make(Modules::class);

        foreach ($modules->installed() as $module) {
            $modules->wire($module);
        }
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atrium');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'atrium');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'atrium');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/atrium.php' => config_path('atrium.php'),
        ], 'atrium-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'atrium-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/atrium'),
        ], 'atrium-views');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/atrium'),
        ], 'atrium-lang');

        $this->publishes([
            __DIR__.'/../dist' => public_path('vendor/atrium'),
        ], ['atrium-assets', 'laravel-assets']);
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Optional: modules with hand-written controllers live in the
        // application's routes/atrium.php, inside the admin prefix and auth.
        $custom = base_path('routes/atrium.php');

        if (is_file($custom)) {
            $this->app['router']
                ->prefix(config('atrium.prefix', 'admin'))
                ->middleware([...config('atrium.middleware', ['web']), 'atrium.inertia', 'admin.auth', 'admin.can'])
                ->group($custom);
        }
    }

    /**
     * Route::localized(fn () => …) — the public routes of a multilingual site.
     *
     * Each route is registered twice: as is for the default content language
     * (/articles/hello) and under a language prefix for the others
     * (/de/articles/hallo, named "localized.{name}"). A site with one language
     * keeps its addresses exactly as they were. Build links with
     * ContentLanguages::route(), which picks the right one.
     */
    private function registerLocalizedRoutes(): void
    {
        Route::macro('localized', function (Closure $routes): void {
            Route::middleware(['atrium.site', 'atrium.content-locale'])->group($routes);

            Route::prefix('{locale}')
                ->where(['locale' => Language::CODE_PATTERN])
                ->middleware(['atrium.site', 'atrium.content-locale'])
                ->name('localized.')
                ->group($routes);
        });
    }

    /**
     * sitemap.xml is cached: any saved or deleted record with a public page
     * makes it build anew. Languages and sites flush it themselves.
     */
    private function registerSitemapFlush(): void
    {
        $flush = function (string $event, array $payload): void {
            if (($payload[0] ?? null) instanceof Sitemapable) {
                $this->app->make(Sitemap::class)->flush();
            }
        };

        Event::listen('eloquent.saved: *', $flush);
        Event::listen('eloquent.deleted: *', $flush);
    }

    /**
     * The record cache: query macros, dropping what a saved record showed,
     * a clean slate for every request and job.
     */
    private function registerCache(): void
    {
        Builder::macro('cachedIds', function (?int $perPage = null, ?int $page = null, array $dependsOn = []) {
            /** @var Builder<Model> $this */
            return app(RecordCache::class)->ids($this, $perPage, $page, $dependsOn);
        });

        Builder::macro('cachedPaginate', function (int $perPage = 20, string $pageName = 'page', ?int $page = null, ?string $locale = null, array $dependsOn = []) {
            /** @var Builder<Model> $this */
            return app(RecordCache::class)->paginate($this, $perPage, $pageName, $page, $locale, $dependsOn);
        });

        Builder::macro('cachedCards', function (?string $locale = null, array $dependsOn = []) {
            /** @var Builder<Model> $this */
            $cache = app(RecordCache::class);

            return $cache->cards($this->getModel()::class, $cache->ids($this, dependsOn: $dependsOn)->ids, $locale);
        });

        // A cached page depends on the class of every model it read.
        Event::listen('eloquent.retrieved: *', function (string $event): void {
            if (config('atrium.cache.pages.enabled', false)) {
                $this->app->make(RecordCache::class)->markRead(['list:'.substr($event, 20)]);
            }
        });

        $changed = function (string $event, array $payload): void {
            if (($payload[0] ?? null) instanceof Model) {
                $this->app->make(RecordCache::class)->changed($payload[0]);
            }
        };

        foreach (['saved', 'deleted', 'restored', 'forceDeleted'] as $event) {
            Event::listen('eloquent.'.$event.': *', $changed);
        }

        $reset = function (): void {
            if ($this->app->resolved(RecordCache::class)) {
                $cache = $this->app->make(RecordCache::class);
                $cache->persistStats();
                $cache->reset();
            }
        };

        $this->app->terminating($reset);
        Event::listen(JobProcessing::class, $reset);

        // The monitoring module reads this request's hits and misses at the
        // very end, after reset() may already have closed them.
        $this->app->make(Hooks::class)->filter('atrium.cache.stats', function (array $stats): array {
            $own = $this->app->make(RecordCache::class)->finishedStats();

            return [
                'hits' => (int) ($stats['hits'] ?? 0) + $own['hits'],
                'misses' => (int) ($stats['misses'] ?? 0) + $own['misses'],
            ] + $stats;
        });
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Console\Commands\CacheWarmCommand::class,
            Console\Commands\CdnPurgeCommand::class,
            Console\Commands\CreateAdminCommand::class,
            Console\Commands\IndexMediaCommand::class,
            Console\Commands\InstallCommand::class,
            Console\Commands\MakeModuleCommand::class,
            Console\Commands\MakePackageCommand::class,
            Console\Commands\ModuleInstallCommand::class,
            Console\Commands\ModuleRemoveCommand::class,
            Console\Commands\SlugRegenerateCommand::class,
        ]);
    }
}
