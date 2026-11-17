<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Modules\Module;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * A module package of its own, to publish on Packagist: the skeleton in
 * stubs/module (manifest, migration, model, demo seeder, tests, CI) with the
 * names filled in. `atrium:make-module` makes a model in the application
 * instead.
 */
final class MakePackageCommand extends Command
{
    private const NAME = '/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$/';

    protected $signature = 'atrium:make-package
                            {package : Composer name of the new module, e.g. acme/atrium-reviews}
                            {--model= : Model class (defaults to the singular of the name, e.g. Review)}
                            {--namespace= : PHP namespace (defaults to Vendor\\Name, e.g. Acme\\Reviews)}
                            {--title= : Name shown in the admin (defaults to the name, e.g. Reviews)}
                            {--path= : Folder to create it in (defaults to packages/{name})}
                            {--force : Write into a folder that is not empty}';

    protected $description = 'Create a module package to publish: manifest, migration, model, demo seeder, tests and CI';

    public function handle(Filesystem $files): int
    {
        $package = strtolower(trim((string) $this->argument('package')));

        if (! preg_match(self::NAME, $package)) {
            $this->components->error("{$package} is not a Composer package name: vendor/atrium-name.");

            return self::FAILURE;
        }

        [$vendor, $name] = explode('/', $package);
        $key = Module::keyOf($package);
        $class = Str::studly($key);
        $model = Str::studly((string) ($this->option('model') ?: Str::singular($class)));
        $table = Str::snake(Str::pluralStudly($model));
        $table = str_starts_with($table, $key) ? $table : "{$key}_{$table}";
        $namespace = trim(str_replace('/', '\\', (string) ($this->option('namespace') ?: Str::studly($vendor).'\\'.$class)), '\\');
        // It goes into JSON, PHP strings and Markdown alike: no quotes.
        $title = str_replace(["'", '"', '\\'], '', (string) ($this->option('title') ?: Str::headline($key)));
        $path = (string) ($this->option('path') ?: base_path('packages/'.Str::after($name, 'atrium-')));

        if ($files->isDirectory($path) && ! $files->isEmptyDirectory($path) && ! $this->option('force')) {
            $this->components->error("{$path} is not empty. Use --force to write into it.");

            return self::FAILURE;
        }

        $values = [
            '{{ package }}' => $package,
            '{{ vendor }}' => Str::headline($vendor),
            '{{ key }}' => $key,
            '{{ class }}' => $class,
            '{{ model }}' => $model,
            '{{ table }}' => $table,
            '{{ namespace }}' => $namespace,
            '{{ namespace_json }}' => str_replace('\\', '\\\\', $namespace),
            '{{ title }}' => $title,
            '{{ year }}' => date('Y'),
        ];
        $names = ['__date__' => date('Y_m_d').'_000001', '__class__' => $class, '__model__' => $model, '__table__' => $table, '__key__' => $key];

        $stubs = dirname(__DIR__, 3).'/stubs/module';

        foreach (Finder::create()->files()->in($stubs)->ignoreDotFiles(false)->ignoreVCS(false)->name('/\.stub$/')->sortByName() as $stub) {
            $target = $path.'/'.strtr(substr($stub->getRelativePathname(), 0, -strlen('.stub')), $names);
            $files->ensureDirectoryExists(dirname($target));
            $files->put($target, strtr($stub->getContents(), $values));
        }

        $relative = Str::after($path, base_path().'/');

        $this->components->info("{$package} is in {$path}.");
        $this->line('  Try it in this project:');
        $this->line("    composer config repositories.{$key} path {$relative}");
        $this->line("    composer require {$package}:@dev");
        $this->line("    php artisan atrium:module:install {$package} --demo");
        $this->line("  Its own tests: cd {$relative} && composer install && vendor/bin/phpunit");

        return self::SUCCESS;
    }
}
