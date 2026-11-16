<?php

declare(strict_types=1);

namespace Atrium\Admin;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Http\Middleware\AdminAuthenticate;
use Atrium\Admin\Http\Middleware\AuthenticateApiToken;
use Atrium\Admin\Http\Middleware\HandleAtriumRequests;
use Atrium\Admin\Http\Middleware\RecordPageTiming;
use Atrium\Admin\Http\Middleware\RedirectIfAdminAuthenticated;
use Atrium\Admin\Http\Middleware\SlugRedirectMiddleware;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Services\SlugService;
use Atrium\Admin\Support\Locales;
use Atrium\Admin\Support\Menu;
use Illuminate\Routing\Router;
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
    }

    public function boot(): void
    {
        $this->registerInertiaPages();
        $this->registerMiddleware();
        $this->registerResources();
        $this->registerRoutes();
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
