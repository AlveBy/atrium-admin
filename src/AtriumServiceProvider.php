<?php

declare(strict_types=1);

namespace Atrium\Admin;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Http\Middleware\AdminAuthenticate;
use Atrium\Admin\Http\Middleware\AuthenticateApiToken;
use Atrium\Admin\Http\Middleware\HandleAtriumRequests;
use Atrium\Admin\Http\Middleware\RecordPageTiming;
use Atrium\Admin\Http\Middleware\RedirectIfAdminAuthenticated;
use Atrium\Admin\Http\Middleware\ResolveSite;
use Atrium\Admin\Http\Middleware\SetContentLocale;
use Atrium\Admin\Http\Middleware\SlugRedirectMiddleware;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Services\SlugService;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Locales;
use Atrium\Admin\Support\Menu;
use Atrium\Admin\Support\Sites;
use Closure;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
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
        $this->app->singleton(ResourceRegistry::class);
        $this->app->singleton(ApiResources::class);
        $this->app->singleton(Menu::class);
        $this->app->singleton(Locales::class);
        $this->app->singleton(ContentLanguages::class);
        $this->app->singleton(Sites::class);
    }

    public function boot(): void
    {
        $this->registerInertiaPages();
        $this->registerMiddleware();
        $this->registerResources();
        $this->registerRoutes();
        $this->registerLocalizedRoutes();
        $this->registerCommands();
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
        $router->aliasMiddleware('admin.guest', RedirectIfAdminAuthenticated::class);
        $router->aliasMiddleware('admin.api', AuthenticateApiToken::class);
        $router->aliasMiddleware('atrium.inertia', HandleAtriumRequests::class);
        $router->aliasMiddleware('slug.redirect', SlugRedirectMiddleware::class);
        $router->aliasMiddleware('atrium.content-locale', SetContentLocale::class);
        $router->aliasMiddleware('atrium.site', ResolveSite::class);

        // Every page of the app knows its site, picked by domain.
        $router->pushMiddlewareToGroup('web', ResolveSite::class);

        // Page timings feed the dashboard gadgets. Pushed here so the gadgets
        // work right after install, without editing bootstrap/app.php.
        if (config('atrium.dashboard.timings.enabled', true)) {
            $router->pushMiddlewareToGroup('web', RecordPageTiming::class);
        }
    }

    private function registerResources(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
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

        // Optional: modules with hand-written controllers live in the
        // application's routes/atrium.php, inside the admin prefix and auth.
        $custom = base_path('routes/atrium.php');

        if (is_file($custom)) {
            $this->app['router']
                ->prefix(config('atrium.prefix', 'admin'))
                ->middleware([...config('atrium.middleware', ['web']), 'atrium.inertia', 'admin.auth'])
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

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Console\Commands\CreateAdminCommand::class,
            Console\Commands\IndexMediaCommand::class,
            Console\Commands\InstallCommand::class,
            Console\Commands\MakeModuleCommand::class,
            Console\Commands\SlugRegenerateCommand::class,
        ]);
    }
}
