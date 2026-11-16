<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Modules\Soft;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Support\ConfigWriter;
use Atrium\Admin\Support\Menu;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

/**
 * A module package from `composer require` to `atrium:module:remove`.
 */
final class ModulesTest extends TestCase
{
    private const QUESTION = 'Keep the data of acme/atrium-labels (its tables and records)? If not, they are dropped';

    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(Modules::class, static fn (Modules $modules) => $modules->add(Label::module()));

        // The commands edit config/atrium.php: a copy, never the package's.
        $this->root = sys_get_temp_dir().'/atrium-modules-'.uniqid();
        mkdir($this->root.'/config', 0777, true);
        copy(dirname(__DIR__, 2).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_the_manifest_is_read_from_composer_json(): void
    {
        $module = $this->app->make(Modules::class)->find('acme/atrium-labels');

        $this->assertNotNull($module);
        $this->assertSame([Label::class], $module->resources);
        $this->assertSame([Label::class => ['prefix' => 'labels']], $module->publicUrls);
        $this->assertSame([Label::class => ['title' => 'name']], $module->search);
        $this->assertSame([realpath(dirname(__DIR__)).'/Fixtures/Modules/labels/database/migrations'], array_map(static fn (string $path): string => (string) realpath($path), $module->migrations));
        $this->assertNull(Module::fromPath(dirname(__DIR__, 2)));
    }

    public function test_a_module_says_what_site_search_reads_of_it_and_the_application_has_the_last_word(): void
    {
        $modules = $this->app->make(Modules::class);
        $module = $modules->find('acme/atrium-labels');
        $this->assertNotNull($module);

        config(['atrium.search' => ['App\\Models\\Post' => false]]);
        $modules->wire($module);
        $this->assertSame(['App\\Models\\Post' => false, Label::class => ['title' => 'name']], config('atrium.search'));

        config(['atrium.search' => [Label::class => false]]);
        $modules->wire($module);
        $this->assertSame([Label::class => false], config('atrium.search'));

        config(['atrium.search' => null]);
        $modules->wire($module);
        $this->assertSame([Label::class => ['title' => 'name']], config('atrium.search'));
    }

    public function test_a_module_without_a_word_on_search_leaves_it_alone(): void
    {
        $module = Module::fromPath(dirname(__DIR__).'/Fixtures/Modules/labels-printing');
        $this->assertNotNull($module);
        $this->assertSame([], $module->search);
    }

    public function test_a_required_module_stays_out_of_the_panel_until_it_is_installed(): void
    {
        $this->assertArrayNotHasKey('labels', $this->app->make(ResourceRegistry::class)->all());
        $this->assertFalse(Soft::available(Label::class));
        $this->assertFalse(Schema::hasTable('labels'));
        $this->assertNull(config('atrium.public_urls.'.Label::class));

        // Even when its service provider pushes the model to the resources.
        config()->push('atrium.resources', Label::class);
        $this->app->make(ResourceRegistry::class)->flush();

        $this->assertArrayNotHasKey('labels', $this->app->make(ResourceRegistry::class)->all());
    }

    public function test_install_runs_its_migrations_and_puts_it_in_the_menu(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'Acme/Atrium-Labels'])
            ->expectsOutputToContain('acme/atrium-labels is installed')
            ->assertSuccessful();

        $this->assertTrue(Schema::hasTable('labels'));
        $this->assertTrue(Soft::available(Label::class));
        $this->assertSame(Label::class, $this->app->make(ResourceRegistry::class)->model('labels'));
        $this->assertContains('admin.labels.index', array_column($this->app->make(Menu::class)->items(), 'route'));
        $this->assertSame(['prefix' => 'labels'], config('atrium.public_urls.'.Label::class));

        $config = require $this->root.'/config/atrium.php';
        $this->assertSame(['acme/atrium-labels'], $config['modules']);

        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])
            ->expectsOutputToContain('installed already')
            ->assertSuccessful();

        $this->assertSame(['acme/atrium-labels'], (require $this->root.'/config/atrium.php')['modules']);
    }

    public function test_a_package_composer_does_not_have_is_not_installed(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-nothing'])
            ->expectsOutputToContain('composer require acme/atrium-nothing')
            ->assertFailed();

        $this->assertSame([], config('atrium.modules'));
    }

    public function test_remove_drops_the_data_when_asked_and_takes_the_module_out_of_the_roles(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        Label::query()->create(['name' => 'Red']);
        $role = AdminRole::query()->create(['name' => 'Editors', 'permissions' => ['labels.view', 'labels.update', 'posts.view']]);

        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels'])
            ->expectsConfirmation(self::QUESTION, 'no')
            ->expectsOutputToContain('composer remove acme/atrium-labels')
            ->assertSuccessful();

        $this->assertFalse(Schema::hasTable('labels'));
        $this->assertSame(['posts.view'], $role->fresh()?->permissions);
        $this->assertSame([], (require $this->root.'/config/atrium.php')['modules']);
        $this->assertArrayNotHasKey('labels', $this->app->make(ResourceRegistry::class)->all());
        $this->assertFalse(Soft::available(Label::class));
    }

    public function test_remove_keeps_the_data_by_default(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        Label::query()->create(['name' => 'Red']);

        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels'])
            ->expectsConfirmation(self::QUESTION, 'yes')
            ->expectsOutputToContain('its data is kept')
            ->assertSuccessful();

        $this->assertSame(1, Label::query()->count());
        $this->assertSame([], config('atrium.modules'));

        // Installed again, the records are where they were.
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->assertSame('Red', Label::query()->value('name'));
    }

    public function test_remove_asks_nothing_with_an_option(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();

        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels', '--delete-data' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('labels'));

        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels'])
            ->expectsOutputToContain('is not installed')
            ->assertSuccessful();
    }

    public function test_a_config_published_before_modules_gets_the_list(): void
    {
        $path = $this->root.'/config/old.php';
        file_put_contents($path, "<?php\n\nreturn [\n    'prefix' => 'admin',\n\n    'resources' => [\n        App\\Models\\Article::class,\n    ],\n\n    'demo' => false,\n];\n");

        $this->assertTrue(ConfigWriter::addModule('acme/one', $path));
        $this->assertTrue(ConfigWriter::addModule('acme/two', $path));
        $this->assertTrue(ConfigWriter::addModule('acme/one', $path));

        $config = require $path;
        $this->assertSame(['acme/one', 'acme/two'], $config['modules']);
        $this->assertSame(['App\\Models\\Article'], $config['resources']);
        $this->assertFalse($config['demo']);

        $this->assertTrue(ConfigWriter::removeModule('acme/one', $path));
        $this->assertSame(['acme/two'], (require $path)['modules']);
        $this->assertFalse(ConfigWriter::addModule('acme/one', '/nonexistent/atrium.php'));
    }

    public function test_a_module_s_suggestions_come_first(): void
    {
        $catalog = new Module('acme/catalog', suggests: ['acme/brands', 'acme/absent']);
        $brands = new Module('acme/brands', suggests: ['acme/catalog']);
        $news = new Module('acme/news');

        $order = $this->app->make(Modules::class)->dependencyOrder(['acme/catalog' => $catalog, 'acme/news' => $news, 'acme/brands' => $brands]);

        $this->assertSame(['acme/brands', 'acme/catalog', 'acme/news'], array_keys($order));
    }
}
