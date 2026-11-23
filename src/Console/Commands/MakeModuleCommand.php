<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Support\ConfigWriter;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Scaffold a module: a model that implements Administrable, its migration,
 * and an entry in config/atrium.php. No controller and no pages — the panel
 * serves every module with the same ones.
 */
final class MakeModuleCommand extends Command
{
    protected $signature = 'atrium:make-module
                            {name : Model name, e.g. Article}
                            {--label= : Name shown in the menu (defaults to the plural of the model)}
                            {--no-migration : Do not create a migration}
                            {--force : Overwrite an existing model}';

    protected $description = 'Create an Atrium module: model, migration and config entry';

    public function handle(Filesystem $files): int
    {
        $class = Str::studly((string) $this->argument('name'));
        $namespace = trim($this->laravel->getNamespace(), '\\').'\\Models';
        $fqcn = $namespace.'\\'.$class;
        $table = Str::snake(Str::pluralStudly($class));
        $label = $this->option('label');
        if (! is_string($label) || $label === '') {
            $label = Str::headline(Str::pluralStudly($class));
        }

        $modelPath = app_path("Models/{$class}.php");

        if ($files->exists($modelPath) && ! $this->option('force')) {
            $this->components->error("{$modelPath} already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($modelPath));
        $files->put($modelPath, $this->render($files, 'model', [
            'namespace' => $namespace,
            'class' => $class,
            'label' => addcslashes($label, "'\\"),
            'table' => $table,
        ]));
        $this->components->info("Model [{$modelPath}] created.");

        $this->writeLabel($files, $table, $label);

        if (! $this->option('no-migration')) {
            $migrationPath = database_path('migrations/'.date('Y_m_d_His')."_create_{$table}_table.php");
            $files->put($migrationPath, $this->render($files, 'migration', ['table' => $table]));
            $this->components->info("Migration [{$migrationPath}] created.");
        }

        if (ConfigWriter::addResource($fqcn)) {
            $this->components->info('Module registered in [config/atrium.php].');
        } else {
            $this->components->warn("Add {$fqcn}::class to 'resources' in config/atrium.php (run atrium:install first to publish it).");
        }

        return self::SUCCESS;
    }

    /**
     * The module's name goes to the language files, so that the menu speaks
     * the language of the panel: the model reads it with __(). The file of the
     * fallback language is written, every other language falls back to it;
     * translate it by adding lang/{locale}/{table}.php.
     */
    private function writeLabel(Filesystem $files, string $table, string $label): void
    {
        $locale = config()->string('app.fallback_locale', 'en');
        $path = lang_path("{$locale}/{$table}.php");

        if ($files->exists($path) && ! $this->option('force')) {
            $this->components->warn("{$path} already exists, so the name of the module is not written there. Add 'module' => '{$label}' to it.");

            return;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $this->render($files, 'lang', ['label' => addcslashes($label, "'\\")]));
        $this->components->info("Name [{$path}] created.");
    }

    /**
     * @param  array<string,string>  $replace
     */
    private function render(Filesystem $files, string $stub, array $replace): string
    {
        $custom = base_path("stubs/atrium/{$stub}.stub");
        $path = is_file($custom) ? $custom : __DIR__."/../../../stubs/{$stub}.stub";

        $content = $files->get($path);

        foreach ($replace as $key => $value) {
            $content = str_replace('{{ '.$key.' }}', $value, $content);
        }

        return $content;
    }
}
