<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Facades\Setting;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Modules\Settings;
use Atrium\Admin\Support\Menu;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\Fixtures\Modules\LabelSettings;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/**
 * A module's settings: declared by its manifest, set on a screen of the
 * panel, read with Setting::get().
 */
final class ModuleSettingsTest extends TestCase
{
    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(Modules::class, static fn (Modules $modules) => $modules->add(Label::module()));

        $this->root = sys_get_temp_dir().'/atrium-settings-'.uniqid();
        mkdir($this->root.'/config', 0777, true);
        copy(dirname(__DIR__, 2).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_the_manifest_names_the_settings_and_the_key(): void
    {
        $module = Label::module();

        $this->assertSame(LabelSettings::class, $module->settings);
        $this->assertSame('labels', $module->key());
        $this->assertSame('Labels', $module->label());
        $this->assertSame(['labels.settings', 'labels.print', 'posts.label'], $module->permissions());
    }

    public function test_a_module_that_is_not_installed_has_no_settings(): void
    {
        $this->assertNull(Setting::get('labels.color'));
        $this->assertSame('fallback', Setting::get('labels.color', 'fallback'));
        $this->assertSame([], Setting::all('labels'));
        $this->assertNull(Setting::get('nothing.at_all'));
    }

    public function test_a_value_nobody_saved_is_the_fields_default(): void
    {
        $this->install();

        $this->assertSame('red', Setting::get('labels.color'));
        $this->assertNull(Setting::get('labels.per_page'));
        $this->assertSame(12, Setting::get('labels.per_page', 12));
        $this->assertSame(['color' => 'red', 'per_page' => null, 'show_count' => null], Setting::all('labels'));
    }

    public function test_a_key_is_the_module_then_the_field_and_nothing_else_names_a_value(): void
    {
        $this->install();

        $this->assertSame('red', Setting::get('labels.color'));
        $this->assertSame('fallback', Setting::get('labels.color.extra', 'fallback'), 'Everything after the first dot is the field.');
        $this->assertSame('fallback', Setting::get('labels', 'fallback'));
        $this->assertSame('fallback', Setting::get('', 'fallback'));
    }

    public function test_a_value_saved_empty_is_the_default_but_zero_and_false_are_values(): void
    {
        $this->install();
        $settings = $this->app->make(Settings::class);

        $settings->save('labels', ['color' => '', 'per_page' => 0, 'show_count' => false]);
        $settings->flush();
        $this->assertSame(['color' => 'red', 'per_page' => 0, 'show_count' => false], Setting::all('labels'));

        $settings->save('labels', ['color' => null]);
        $settings->flush();
        $this->assertSame('red', Setting::get('labels.color'));

        $settings->save('labels', ['color' => 'blue']);
        $settings->flush();
        $this->assertSame('blue', Setting::get('labels.color'));
    }

    public function test_a_superadmin_sets_up_a_module(): void
    {
        $this->install();
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('/admin/modules/labels/settings')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/Settings')
                ->where('module.key', 'labels')
                ->where('description', 'How labels look on the site.')
                ->where('fields.0.name', 'color')
                ->where('fields.0.default', 'red')
                ->where('site', null));

        $this->actingAs($admin, 'admin')
            ->put('/admin/modules/labels/settings', ['color' => 'blue', 'per_page' => '24', 'show_count' => true])
            ->assertRedirect('/admin/modules/labels/settings')
            ->assertSessionHasNoErrors();

        $this->app->make(Settings::class)->flush();

        $this->assertSame('blue', Setting::get('labels.color'));
        $this->assertSame('24', Setting::get('labels.per_page'));
        $this->assertTrue(Setting::get('labels.show_count'));

        // A field emptied is back to its default.
        $this->actingAs($admin, 'admin')->put('/admin/modules/labels/settings', ['color' => 'red', 'per_page' => null, 'show_count' => false]);
        $this->app->make(Settings::class)->flush();

        $this->assertSame(['color' => 'red', 'per_page' => null, 'show_count' => false], Setting::all('labels'));
        $this->assertSame(1, DB::table(Settings::TABLE)->count());
    }

    public function test_the_values_are_checked_by_the_fields_rules(): void
    {
        $this->install();

        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/modules/labels/settings')
            ->put('/admin/modules/labels/settings', ['color' => 'green', 'per_page' => '0'])
            ->assertRedirect('/admin/modules/labels/settings')
            ->assertSessionHasErrors(['color', 'per_page']);

        $this->assertSame(0, DB::table(Settings::TABLE)->count());
    }

    public function test_settings_need_their_permission(): void
    {
        $this->install();

        $this->assertContains('labels.settings', $this->app->make(Permissions::class)->all());

        $editor = $this->editor(['labels.view', 'labels.update']);
        $this->actingAs($editor, 'admin')->get('/admin/modules/labels/settings')->assertForbidden();
        $this->actingAs($editor, 'admin')->put('/admin/modules/labels/settings', ['color' => 'blue'])->assertForbidden();
        $this->actingAs($editor, 'admin')->get('/admin/modules')->assertForbidden();
        $this->assertNotContains('admin.modules.index', array_column($this->app->make(Menu::class)->items($editor), 'route'));

        $setter = $this->editor(['labels.settings'], 'setter@example.com');
        $this->actingAs($setter, 'admin')->get('/admin/modules/labels/settings')->assertOk();
        $this->actingAs($setter, 'admin')->put('/admin/modules/labels/settings', ['color' => 'blue'])->assertSessionHasNoErrors();
        $this->actingAs($setter, 'admin')->get('/admin/modules')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/Index')
                ->where('modules.0.name', 'acme/atrium-labels')
                ->where('modules.0.settings', true));
        $this->assertContains('admin.modules.index', array_column($this->app->make(Menu::class)->items($setter), 'route'));
        $this->assertSame(
            ['label' => __('atrium::menu.modules'), 'route' => 'admin.modules.index', 'icon' => 'settings'],
            collect($this->app->make(Menu::class)->items($setter))->firstWhere('route', 'admin.modules.index'),
        );
    }

    #[DefineEnvironment('installLabels')]
    public function test_the_role_form_offers_the_settings_in_the_modules_row(): void
    {
        $section = collect($this->app->make(Permissions::class)->sections())->firstWhere('key', 'labels');

        $this->assertSame(['view', 'create', 'update', 'delete', 'import', 'export', 'settings', 'print'], $section['actions']);
    }

    public function test_per_site_settings_are_kept_for_each_site(): void
    {
        $declaration = new LabelSettings;
        $declaration->perSite = true;
        $this->app->instance(LabelSettings::class, $declaration);
        $this->install();

        $other = Site::query()->create(['name' => 'Outlet', 'code' => 'outlet', 'domains' => 'outlet.test', 'is_active' => true]);
        $admin = $this->admin();
        $sites = $this->app->make(Sites::class);

        $this->actingAs($admin, 'admin')->put('/admin/modules/labels/settings', ['color' => 'blue']);
        $this->actingAs($admin, 'admin')->post('/admin/site', ['site_id' => $other->id]);
        $this->actingAs($admin, 'admin')->get('/admin/modules/labels/settings')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('site.name', 'Outlet')->where('values', []));
        $this->actingAs($admin, 'admin')->put('/admin/modules/labels/settings', ['color' => 'red', 'per_page' => '5']);

        $settings = $this->app->make(Settings::class);
        $settings->flush();

        $this->assertSame('blue', Setting::get('labels.color', null, $sites->default()));
        $this->assertSame('red', Setting::get('labels.color', null, $other));
        $this->assertSame('5', Setting::get('labels.per_page', null, $other->id));

        $sites->setCurrent($other);
        $this->assertSame('red', Setting::get('labels.color'));
    }

    public function test_removing_the_module_takes_its_settings_out_of_the_roles_and_drops_them_with_the_data(): void
    {
        $this->install();
        $this->app->make(Settings::class)->save('labels', ['color' => 'blue']);
        $role = AdminRole::query()->create(['name' => 'Setter', 'permissions' => ['labels.settings', 'posts.view']]);

        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels', '--delete-data' => true])->assertSuccessful();

        $this->assertSame(['posts.view'], $role->fresh()->permissions);
        $this->assertSame(0, DB::table(Settings::TABLE)->count());
    }

    /**
     * Installed before the app boots, so its routes are there.
     */
    protected function installLabels($app): void
    {
        $app['config']->set('atrium.modules', ['acme/atrium-labels']);
    }

    private function install(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions, string $email = 'editor@example.com'): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => $email, 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role of '.$email, 'permissions' => $permissions]));

        return $user->fresh();
    }
}
