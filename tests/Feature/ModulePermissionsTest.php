<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Inertia\Testing\AssertableInertia;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/**
 * The permissions a module's manifest declares: in the role form while it
 * is installed, given to the roles they come with, gone when it is removed.
 */
final class ModulePermissionsTest extends TestCase
{
    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(Modules::class, static function (Modules $modules): void {
            $modules->add(Label::module());
            $modules->add(Module::fromComposer(['name' => 'acme/atrium-shop', 'type' => Modules::TYPE, 'extra' => ['atrium' => ['key' => 'store', 'permissions' => [
                'refund' => 'Refund orders',
                'reports.export' => ['label' => 'Export reports'],
                'reports.archive' => 'atrium::menu',
                'reports.quiet' => '',
            ]]]], '/tmp'));
        });

        $this->root = sys_get_temp_dir().'/atrium-permissions-'.uniqid();
        mkdir($this->root.'/config', 0777, true);
        copy(dirname(__DIR__, 2).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_the_manifest_names_them_a_bare_action_belonging_to_the_module(): void
    {
        $this->assertSame([
            'labels.print' => ['label' => 'Print labels', 'with' => null],
            'posts.label' => ['label' => 'Label posts', 'with' => 'posts.update'],
        ], Label::module()->declaredPermissions);

        $module = Module::fromComposer(['name' => 'acme/atrium-shop', 'extra' => ['atrium' => ['key' => 'store', 'permissions' => [
            'Not a name!' => 'Skipped',
            'a.b.c' => 'Skipped too',
            'refund' => 'Refund',
        ]]]], '/tmp');

        $this->assertSame(['store.refund'], $module->permissions());
    }

    public function test_they_are_not_offered_while_the_module_is_not_installed(): void
    {
        $this->assertNotContains('labels.print', $this->app->make(Permissions::class)->all());
        $this->assertNotContains('posts.label', $this->app->make(Permissions::class)->all());
    }

    #[DefineEnvironment('installLabels')]
    public function test_the_role_form_offers_them_in_their_sections_rows_with_their_labels(): void
    {
        $sections = collect($this->app->make(Permissions::class)->sections())->keyBy('key');

        $this->assertSame(['view', 'create', 'update', 'delete', 'settings', 'print'], $sections['labels']['actions']);
        $this->assertSame(['print' => 'Print labels'], $sections['labels']['labels']);
        $this->assertSame(['view', 'create', 'update', 'delete', 'label'], $sections['posts']['actions']);
        $this->assertSame(['label' => 'Label posts'], $sections['posts']['labels']);

        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('/admin/roles/create')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Roles/Edit')
                ->where('sections', fn ($sections): bool => collect($sections)->firstWhere('key', 'posts')['labels'] === ['label' => 'Label posts']));

        $this->actingAs($admin, 'admin')
            ->post('/admin/roles', ['name' => 'Printer', 'permissions' => ['labels.view', 'labels.print', 'posts.label']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['labels.view', 'labels.print', 'posts.label'], AdminRole::query()->where('name', 'Printer')->sole()->permissions);
    }

    public function test_installing_gives_them_to_the_roles_they_come_with_and_removing_takes_them_away(): void
    {
        $editor = AdminRole::query()->create(['name' => 'Editor', 'permissions' => ['posts.view', 'posts.update']]);
        $reader = AdminRole::query()->create(['name' => 'Reader', 'permissions' => ['posts.view']]);

        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();

        $this->assertSame(['posts.view', 'posts.update', 'posts.label'], $editor->fresh()->permissions);
        $this->assertSame(['posts.view'], $reader->fresh()->permissions);

        // Taken off a role by hand, it stays off when the module is installed again.
        $editor->refresh()->update(['permissions' => ['posts.view', 'posts.update']]);
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->assertSame(['posts.view', 'posts.update'], $editor->fresh()->permissions);

        $editor->update(['permissions' => ['posts.view', 'posts.update', 'posts.label', 'labels.print']]);
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels', '--keep-data' => true])->assertSuccessful();

        $this->assertSame(['posts.view', 'posts.update'], $editor->fresh()->permissions);
    }

    #[DefineEnvironment('installShop')]
    public function test_a_permission_of_a_section_nobody_else_owns_opens_a_row_of_its_own_with_its_labels(): void
    {
        $sections = collect($this->app->make(Permissions::class)->sections())->keyBy('key');

        // "refund" is the module's own: a row under the module's name.
        $this->assertSame(['key' => 'store', 'label' => 'Store', 'actions' => ['refund'], 'labels' => ['refund' => 'Refund orders']], $sections['store']);
        // "reports" is nobody's key: a row headlined from its name, in the order declared.
        $this->assertSame(
            ['key' => 'reports', 'label' => 'Reports', 'actions' => ['export', 'archive', 'quiet'], 'labels' => ['export' => 'Export reports', 'archive' => 'atrium::menu']],
            $sections['reports'],
        );
        $this->assertContains('reports.quiet', $this->app->make(Permissions::class)->all());
    }

    /**
     * Installed before the app boots, so its routes are there.
     */
    protected function installShop($app): void
    {
        $app['config']->set('atrium.modules', ['acme/atrium-shop']);
    }

    /**
     * Installed before the app boots, so its routes are there.
     */
    protected function installLabels($app): void
    {
        $app['config']->set('atrium.modules', ['acme/atrium-labels']);
    }
}
