<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\Fixtures\Modules\LabelSettings;
use Atrium\Admin\Tests\Fixtures\Modules\NoteSettings;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * The module screen in exact form: the rows it lists and their order, what
 * the settings screen is given, how settings are checked and what switching
 * a module on runs.
 */
final class ModuleControllerExactnessTest extends TestCase
{
    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(Modules::class, static function (Modules $modules): void {
            $modules->add(new Module('acme/atrium-zed', 'The last by label', title: 'Zed'));
            $modules->add(new Module('acme/atrium-plain', 'Without settings', title: 'Plain'));
            $modules->add(new Module('acme/atrium-child', 'A child with settings', settings: LabelSettings::class, title: 'Child', parent: 'acme/atrium-plain'));
            $modules->add(new Module('acme/atrium-note', 'Settings per site', settings: NoteSettings::class, title: 'Note'));
            $modules->add(Label::module());
        });

        $app['config']->set('atrium.modules', ['acme/atrium-zed', 'acme/atrium-plain', 'acme/atrium-child', 'acme/atrium-note']);

        $this->root = sys_get_temp_dir().'/atrium-modules-exact-'.uniqid();
        mkdir($this->root.'/config', 0777, true);
        copy(dirname(__DIR__, 2).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    private function editor(array $permissions): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role', 'permissions' => $permissions]));

        return $user->fresh();
    }

    public function test_a_superadmin_sees_every_module_with_its_children_right_under_it(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin/modules')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/Index')
                ->where('canManage', true)
                ->where('title', __('atrium::modules.title'))
                ->where('modules', [
                    ['name' => 'acme/atrium-note', 'key' => 'note', 'label' => 'Note', 'description' => 'Settings per site', 'settings' => true, 'enabled' => true, 'parent' => null],
                    ['name' => 'acme/atrium-plain', 'key' => 'plain', 'label' => 'Plain', 'description' => 'Without settings', 'settings' => false, 'enabled' => true, 'parent' => null],
                    ['name' => 'acme/atrium-child', 'key' => 'child', 'label' => 'Child', 'description' => 'A child with settings', 'settings' => true, 'enabled' => true, 'parent' => 'acme/atrium-plain'],
                    ['name' => 'acme/atrium-zed', 'key' => 'zed', 'label' => 'Zed', 'description' => 'The last by label', 'settings' => false, 'enabled' => true, 'parent' => null],
                ]));
    }

    public function test_someone_who_sets_up_one_module_sees_only_that_one_and_a_child_without_its_parent_stands_alone(): void
    {
        $this->actingAs($this->editor(['child.settings']), 'admin')->get('/admin/modules')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canManage', false)
                ->where('required', [])
                ->where('modules', [
                    ['name' => 'acme/atrium-child', 'key' => 'child', 'label' => 'Child', 'description' => 'A child with settings', 'settings' => true, 'enabled' => true, 'parent' => 'acme/atrium-plain'],
                ]));
    }

    public function test_a_module_with_nothing_to_set_up_does_not_hide_the_ones_after_it(): void
    {
        // "plain" has no settings and comes before "child" and "note" in the list.
        $this->actingAs($this->editor(['note.settings', 'child.settings']), 'admin')->get('/admin/modules')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('modules.0.key', 'child')
                ->where('modules.1.key', 'note')
                ->has('modules', 2));
    }

    public function test_the_settings_screen_is_given_the_module_its_fields_values_and_site(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->get('/admin/modules/note/settings', ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);
        $page = $response->json();

        $this->assertSame('Admin/Modules/Settings', $page['component']);
        $this->assertSame(['key' => 'note', 'name' => 'acme/atrium-note', 'label' => 'Note'], $page['props']['module']);
        $this->assertSame(['motto', 'badge'], array_column($page['props']['fields'], 'name'));
        $this->assertSame('Badge', $page['props']['fields'][1]['label']);
        $this->assertSame(__('atrium::modules.settings_title', ['module' => 'Note']), $page['props']['title']);
        $this->assertSame(['id', 'name'], array_keys($page['props']['site']));
        $this->assertStringContainsString('"values":{}', (string) $response->getContent(), 'No values yet: an object, not a list.');

        $this->actingAs($admin, 'admin')->get('/admin/modules/child/settings')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('site', null));
    }

    public function test_the_settings_of_a_module_per_site_name_the_site_and_without_any_site_name_none(): void
    {
        $admin = $this->admin();
        $main = $this->app->make(Sites::class)->default();

        $this->actingAs($admin, 'admin')->get('/admin/modules/note/settings')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('site', ['id' => $main->id, 'name' => $main->name]));

        DB::table('admin_sites')->delete();
        $this->app->make(Sites::class)->flush();

        $this->actingAs($admin, 'admin')->get('/admin/modules/note/settings')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('site', null));
    }

    public function test_the_error_for_a_field_without_a_label_names_the_field(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/modules/note/settings')
            ->put('/admin/modules/note/settings', ['motto' => ''])
            ->assertSessionHasErrors(['motto' => __('validation.required', ['attribute' => 'motto'])]);
    }

    public function test_a_file_sent_for_a_field_is_checked_as_an_upload_and_a_path_as_a_path(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put('/admin/modules/note/settings', ['motto' => 'Hi', 'badge' => UploadedFile::fake()->create('badge.txt', 4, 'text/plain')])
            ->assertSessionHasErrors('badge');

        $this->actingAs($admin, 'admin')
            ->put('/admin/modules/note/settings', ['motto' => 'Hi', 'badge' => 'storage/badge.png'])
            ->assertSessionHasNoErrors();
    }

    public function test_switching_on_a_module_runs_the_migrations_it_shipped_and_only_those(): void
    {
        // A stand-in for the real command, which would migrate the test database.
        $runs = [];
        $stand = new ClosureCommand('migrate {--path=*} {--realpath} {--force}', function () use (&$runs): void {
            $runs[] = [$this->option('path'), $this->option('realpath'), $this->option('force')];
        });
        $this->app->make(ConsoleKernel::class)->registerCommand($stand);

        $this->app['config']->set('atrium.modules', [...config('atrium.modules'), 'acme/atrium-labels']);
        $this->app['config']->set('atrium.disabled_modules', ['acme/atrium-zed', 'acme/atrium-labels']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post('/admin/modules/zed/enable')->assertSessionHas('success');
        $this->assertSame([], $runs, 'A module with no migrations runs none.');

        $this->actingAs($admin, 'admin')->post('/admin/modules/labels/enable')->assertSessionHas('success');
        $this->assertSame([[Label::module()->migrations, true, true]], $runs);
    }
}
