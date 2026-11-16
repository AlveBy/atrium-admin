<?php

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
        $label = (string) ($this->option('label') ?: Str::headline(Str::pluralStudly($class)));

        $modelPath = app_path("Models/{$class}.php");

        if ($files->exists($modelPath) && ! $this->option('force')) {
            $this->components->error("{$modelPath} already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($modelPath));
        $files->put($modelPath, $this->render('model', [
            'namespace' => $namespace,
            'class' => $class,
            'label' => addcslashes($label, "'\\"),
        ]));
        $this->components->info("Model [{$modelPath}] created.");

        if (! $this->option('no-migration')) {
            $migrationPath = database_path('migrations/'.date('Y_m_d_His')."_create_{$table}_table.php");
            $files->put($migrationPath, $this->render('migration', ['table' => $table]));
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
     * @param  array<string,string>  $replace
     */
    private function render(string $stub, array $replace): string
    {
        $custom = base_path("stubs/atrium/{$stub}.stub");
        $path = is_file($custom) ? $custom : __DIR__."/../../../stubs/{$stub}.stub";

        $content = (string) file_get_contents($path);

        foreach ($replace as $key => $value) {
            $content = str_replace('{{ '.$key.' }}', $value, $content);
        }

        return $content;
    }
}
