<?php

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

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
                            {--no-migrate : Do not run migrations}';

    protected $description = 'Install Atrium: config, assets, migrations and the first administrator';

    public function handle(): int
    {
        $this->components->task('Publishing config', fn () => $this->callSilently('vendor:publish', ['--tag' => 'atrium-config']) === 0);
        $this->components->task('Publishing assets', fn () => $this->callSilently('vendor:publish', ['--tag' => 'atrium-assets', '--force' => true]) === 0);

        // API tokens need Sanctum's table; publishing is a no-op when it is already there.
        if (! glob(database_path('migrations/*_create_personal_access_tokens_table.php'))) {
            $this->components->task('Publishing Sanctum migration', fn () => $this->callSilently('vendor:publish', ['--tag' => 'sanctum-migrations']) === 0);
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

        $this->removeStockRobotsTxt();

        $this->newLine();
        $this->components->info('Atrium is ready: '.url(config('atrium.prefix', 'admin')));

        return self::SUCCESS;
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
