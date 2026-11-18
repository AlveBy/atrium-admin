<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Tests\Fixtures\BrandDemoSeeder;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\ShelfDemoSeeder;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * atrium:install: from `composer require` to a working panel. Publishing,
 * migrating and linking are stand-ins here, so the shared test app stays
 * as it is.
 */
final class InstallCommandTest extends TestCase
{
    private string $root;

    /** @var array<int,string> the stand-in commands, in the order they ran */
    private array $ran = [];

    /** @var array<string,int> the exit code a stand-in command gives, 0 by default */
    private array $exits = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->root = sys_get_temp_dir().'/atrium-install-'.getmypid().'-'.uniqid();
        foreach (['config', 'database/migrations', 'public'] as $dir) {
            mkdir($this->root.'/'.$dir, 0777, true);
        }
        copy(dirname(__DIR__, 2).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->useDatabasePath($this->root.'/database');
        $this->app->usePublicPath($this->root.'/public');

        // Inside the callback $this is the command; the list is shared by reference.
        $ran = &$this->ran;
        $exits = &$this->exits;
        foreach (['vendor:publish {--tag=} {--force}', 'migrate {--force}', 'storage:link'] as $signature) {
            $this->app->make(ConsoleKernel::class)->registerCommand(new ClosureCommand($signature, function () use (&$ran, &$exits, $signature): int {
                $tag = $this->hasOption('tag') ? $this->option('tag') : null;
                $force = $this->hasOption('force') && $this->option('force');
                $ran[] = strtok($signature, ' ').($tag ? " {$tag}" : '').($force ? ' --force' : '');

                return $exits[strtok($signature, ' ')] ?? 0;
            }));
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * @param  array<string,mixed>  $options
     */
    private function install(array $options = []): PendingCommand
    {
        return $this->artisan('atrium:install', $options + ['--name' => 'Ann', '--email' => 'ann@example.com', '--password' => 'long-enough']);
    }

    private function onlyModules(Module ...$modules): void
    {
        $registry = $this->app->make(Modules::class);
        (new ReflectionProperty($registry, 'modules'))->setValue($registry, []);

        foreach ($modules as $module) {
            $registry->add($module);
        }
    }

    public function test_a_fresh_install_publishes_migrates_links_and_creates_the_first_administrator(): void
    {
        file_put_contents($this->root.'/public/robots.txt', "User-agent: *\nDisallow:\n");

        $this->install()
            ->expectsOutputToContain('Create the first administrator.')
            ->expectsOutputToContain('Atrium is ready: http://localhost/admin')
            ->doesntExpectOutputToContain('is served instead')
            ->assertSuccessful();

        $this->assertSame([
            'vendor:publish atrium-config',
            'vendor:publish atrium-assets --force',
            'vendor:publish sanctum-migrations',
            'migrate --force',
            'storage:link',
        ], $this->ran);
        $this->assertTrue(AdminUser::query()->where('email', 'ann@example.com')->sole()->isSuperadmin());
        $this->assertFileDoesNotExist($this->root.'/public/robots.txt', 'Laravel\'s stock robots.txt would hide the one set in the admin.');
    }

    public function test_what_is_there_already_is_left_alone(): void
    {
        $this->admin();
        touch($this->root.'/database/migrations/2026_01_01_000000_create_personal_access_tokens_table.php');
        mkdir($this->root.'/public/storage');
        file_put_contents($this->root.'/public/robots.txt', "User-agent: *\nDisallow: /secret\n");

        $this->install()
            ->doesntExpectOutputToContain('Create the first administrator.')
            ->expectsOutputToContain('public/robots.txt is served instead of the robots.txt set in the admin.')
            ->assertSuccessful();

        $this->assertSame(['vendor:publish atrium-config', 'vendor:publish atrium-assets --force', 'migrate --force'], $this->ran);
        $this->assertSame(1, AdminUser::query()->count());
        $this->assertFileExists($this->root.'/public/robots.txt');
    }

    public function test_a_robots_txt_is_kept_quietly_when_the_admin_does_not_serve_one(): void
    {
        config(['atrium.robots' => false]);
        file_put_contents($this->root.'/public/robots.txt', "User-agent: *\nDisallow:\n");

        $this->install()->doesntExpectOutputToContain('robots.txt')->assertSuccessful();

        $this->assertFileExists($this->root.'/public/robots.txt');
    }

    public function test_no_migrate_skips_migrations_and_the_administrator(): void
    {
        $this->install(['--no-migrate' => true])->assertSuccessful();

        $this->assertNotContains('migrate --force', $this->ran);
        $this->assertSame(0, AdminUser::query()->count());
    }

    public function test_a_refused_administrator_stops_the_install(): void
    {
        $this->install(['--password' => 'short'])
            ->expectsOutputToContain('Password must be at least 8 characters.')
            ->doesntExpectOutputToContain('Atrium is ready')
            ->assertFailed();

        $this->assertNotContains('storage:link', $this->ran);
        $this->assertSame(0, AdminUser::query()->count());
    }

    public function test_demo_without_module_packages_says_what_to_require(): void
    {
        $this->onlyModules();

        $this->install(['--demo' => true])
            ->expectsOutputToContain('No module packages to fill')
            ->assertSuccessful();

        $this->assertSame([], config('atrium.modules'));
    }

    public function test_demo_installs_every_module_and_fills_the_ones_others_build_on_first(): void
    {
        $this->onlyModules(
            new Module(name: 'acme/atrium-shelves', demo: ShelfDemoSeeder::class, suggests: ['acme/atrium-brands']),
            new Module(name: 'acme/atrium-brands', demo: BrandDemoSeeder::class),
            new Module(name: 'acme/atrium-plain'),
        );

        $this->install(['--demo' => true])
            ->expectsOutputToContain('Installing acme/atrium-shelves')
            ->expectsOutputToContain('Filling acme/atrium-brands')
            ->doesntExpectOutputToContain('Filling acme/atrium-plain')
            ->doesntExpectOutputToContain('nothing was added')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['acme/atrium-brands', 'acme/atrium-plain', 'acme/atrium-shelves'], (require $this->root.'/config/atrium.php')['modules']);
        $this->assertSame(['Demo brand', 'Demo shelf'], Post::query()->orderBy('id')->pluck('name')->all());

        $this->install(['--demo' => true])
            ->doesntExpectOutputToContain('Installing acme/atrium-shelves')
            ->expectsOutputToContain('The demo already has content; nothing was added.')
            ->assertSuccessful();
        $this->assertSame(2, Post::query()->count());
    }

    public function test_demo_without_migrations_asks_to_migrate_first(): void
    {
        $this->onlyModules(new Module(name: 'acme/atrium-shelves', demo: ShelfDemoSeeder::class));

        $this->install(['--demo' => true, '--no-migrate' => true])
            ->expectsOutputToContain('The demo needs the modules\' tables')
            ->assertSuccessful();

        $this->assertSame(0, Post::query()->count());
        $this->assertSame(['acme/atrium-shelves'], config('atrium.modules'));
    }

    /**
     * What the command printed, as one text.
     *
     * @param  array<string,mixed>  $options
     */
    private function printed(array $options = []): string
    {
        $output = new BufferedOutput;
        Artisan::call('atrium:install', $options + ['--name' => 'Ann', '--email' => 'ann@example.com', '--password' => 'long-enough'], $output);

        return $output->fetch();
    }

    /**
     * @return array<string,array{int,string}>
     */
    public static function exitCodes(): array
    {
        return ['succeeds' => [0, 'DONE'], 'fails' => [1, 'FAIL']];
    }

    #[DataProvider('exitCodes')]
    public function test_every_step_is_reported_by_what_its_command_returned(int $exit, string $result): void
    {
        $this->exits = ['vendor:publish' => $exit, 'migrate' => $exit, 'storage:link' => $exit];

        $printed = $this->printed();

        foreach (['Publishing config', 'Publishing assets', 'Publishing Sanctum migration', 'Running migrations', 'Linking public/storage'] as $step) {
            $this->assertMatchesRegularExpression('/'.preg_quote($step, '/').'[ .]*\S* '.$result.'/', $printed, $step);
        }
    }

    public function test_the_first_administrator_and_the_closing_line_stand_apart_from_the_steps(): void
    {
        $printed = $this->printed();

        $this->assertMatchesRegularExpression('/DONE\s*\n\s*\n\s*INFO\s+Create the first administrator\./', $printed);
        $this->assertMatchesRegularExpression('/\n\s*\n\s*INFO\s+Atrium is ready/', $printed);
    }

    public function test_an_option_left_out_is_asked_for_by_the_user_command(): void
    {
        $this->artisan('atrium:install', ['--email' => 'ann@example.com', '--password' => 'long-enough'])
            ->expectsQuestion('Enter admin name', 'Asked Ann')
            ->assertSuccessful();

        $this->assertSame('Asked Ann', AdminUser::query()->where('email', 'ann@example.com')->sole()->name);
    }

    public function test_the_stock_robots_txt_is_removed_with_a_step_and_no_warning(): void
    {
        file_put_contents($this->root.'/public/robots.txt', "User-agent: *\nDisallow:\n");

        $printed = $this->printed();

        $this->assertMatchesRegularExpression('/Removing public\/robots\.txt .*DONE/', $printed);
        $this->assertStringNotContainsString('is served instead', $printed);
    }

    public function test_demo_steps_are_reported_as_done_and_a_module_with_content_does_not_hide_one_without(): void
    {
        $this->onlyModules(
            new Module(name: 'acme/atrium-shelves', demo: ShelfDemoSeeder::class, suggests: ['acme/atrium-brands']),
            new Module(name: 'acme/atrium-brands', demo: BrandDemoSeeder::class),
        );
        Post::query()->create(['name' => 'Demo shelf']);

        $printed = $this->printed(['--demo' => true]);

        $this->assertMatchesRegularExpression('/Installing acme\/atrium-shelves[ .]*\S* DONE/', $printed);
        $this->assertMatchesRegularExpression('/Filling acme\/atrium-brands[ .]*\S* DONE/', $printed);
        $this->assertMatchesRegularExpression('/Filling acme\/atrium-shelves[ .]*\S* DONE/', $printed);
        $this->assertStringNotContainsString('nothing was added', $printed);
        $this->assertSame(['Demo shelf', 'Demo brand'], Post::query()->orderBy('id')->pluck('name')->all());
    }
}
