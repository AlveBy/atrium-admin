<?php

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Demo\Demo;
use Atrium\Admin\Demo\DemoSeeder;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Console\Command;

/**
 * One command from `composer require` to a working panel.
 */
final class InstallCommand extends Command
{
    protected $signature = 'atrium:install
                            {--name= : Name of the first administrator}
                            {--email= : Email of the first administrator}
                            {--password= : Password of the first administrator}
                            {--no-migrate : Do not run migrations}
                            {--demo : Turn on the demo modules and fill them with a small shop}';

    protected $description = 'Install Atrium: config, assets, migrations and the first administrator';

    public function handle(): int
    {
        $this->components->task('Publishing config', fn () => $this->callSilently('vendor:publish', ['--tag' => 'atrium-config']) === 0);
        $this->components->task('Publishing assets', fn () => $this->callSilently('vendor:publish', ['--tag' => 'atrium-assets', '--force' => true]) === 0);

        // API tokens need Sanctum's table; publishing is a no-op when it is already there.
        if (! glob(database_path('migrations/*_create_personal_access_tokens_table.php'))) {
            $this->components->task('Publishing Sanctum migration', fn () => $this->callSilently('vendor:publish', ['--tag' => 'sanctum-migrations']) === 0);
        }

        if ($this->option('demo')) {
            $this->components->task('Turning on the demo (ATRIUM_DEMO=true)', fn () => $this->enableDemo());
        }

        if (! $this->option('no-migrate')) {
            $this->components->task('Running migrations', fn () => $this->callSilently('migrate', ['--force' => true]) === 0);

            if (! AdminUser::query()->exists()) {
                $this->newLine();
                $this->components->info('Create the first administrator.');

                $status = $this->call('atrium:user', array_filter([
                    '--name' => $this->option('name'),
                    '--email' => $this->option('email'),
                    '--password' => $this->option('password'),
                ]));

                if ($status !== self::SUCCESS) {
                    return $status;
                }
            }
        }

        if ($this->option('demo')) {
            if ($this->option('no-migrate')) {
                $this->components->warn('The demo needs its tables: run `php artisan migrate` and `php artisan atrium:install --demo` again.');
            } else {
                $this->seedDemo();
            }
        }

        $this->removeStockRobotsTxt();

        $this->newLine();
        $this->components->info('Atrium is ready: '.url(config('atrium.prefix', 'admin')));

        return self::SUCCESS;
    }

    /**
     * On for this run, and in .env for the next ones. Without a .env (the
     * variables come from the server) the flag has to be set there.
     */
    private function enableDemo(): bool
    {
        Demo::register($this->laravel);

        $env = $this->laravel->environmentFilePath();

        if (! is_file($env)) {
            $this->components->warn('No .env file: set ATRIUM_DEMO=true where the environment is configured.');

            return true;
        }

        $contents = (string) file_get_contents($env);

        $contents = preg_match('/^ATRIUM_DEMO=.*$/m', $contents)
            ? (string) preg_replace('/^ATRIUM_DEMO=.*$/m', 'ATRIUM_DEMO=true', $contents)
            : rtrim($contents, "\n")."\n\nATRIUM_DEMO=true\n";

        return file_put_contents($env, $contents) !== false;
    }

    private function seedDemo(): void
    {
        // Uploaded pictures are served from storage/app/public.
        if (! file_exists(public_path('storage'))) {
            $this->components->task('Linking public/storage', fn () => $this->callSilently('storage:link') === 0);
        }

        $seeded = false;
        $this->components->task('Filling the demo', function () use (&$seeded): bool {
            $seeded = (new DemoSeeder)->run();

            return true;
        });

        if (! $seeded) {
            $this->components->info('The demo already has content; nothing was added.');
        }
    }

    /**
     * The web server returns public/robots.txt before Laravel is asked, so
     * the one a fresh Laravel ships with would hide the text set in the
     * admin. Laravel's own file goes; one somebody wrote stays, with a warning.
     */
    private function removeStockRobotsTxt(): void
    {
        $file = public_path('robots.txt');

        if (! config('atrium.robots', true) || ! is_file($file)) {
            return;
        }

        $stock = preg_replace('/\s+/', ' ', trim((string) file_get_contents($file))) === 'User-agent: * Disallow:';

        if ($stock) {
            $this->components->task('Removing public/robots.txt (robots.txt is set in the admin now)', fn () => unlink($file));

            return;
        }

        $this->components->warn('public/robots.txt is served instead of the robots.txt set in the admin. Move its text to Sites → robots.txt and delete the file, or set atrium.robots to false.');
    }
}
