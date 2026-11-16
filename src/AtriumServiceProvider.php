<?php

declare(strict_types=1);

namespace Atrium\Admin;

use Illuminate\Support\ServiceProvider;

final class AtriumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atrium.php', 'atrium');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/atrium.php' => config_path('atrium.php'),
            ], 'atrium-config');
        }
    }
}
