<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * A public demo (docs/demo.md): the reset puts everything back, and the
 * visitor cannot lock the others out or take the panel over.
 */
final class DemoModeTest extends TestCase
{
    /** @var array<int,string> the stand-in commands, in the order they ran */
    private array $ran = [];

    /** @var array<string,mixed> the options `atrium:install` was given */
    private array $installed = [];

    /** @var array<string,int> the exit code a stand-in command gives, 0 by default */
    private array $exits = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.demo.reset', '*/30 * * * *');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // migrate:fresh would empty the shared test database and the installer
        // would publish into the test app: stand-ins that record the call.
        // Inside the callback $this is the command.
        $ran = &$this->ran;
        $installed = &$this->installed;
        $exits = &$this->exits;
        $kernel = $this->app->make(ConsoleKernel::class);
        $kernel->registerCommand(new ClosureCommand('migrate:fresh {--force}', function () use (&$ran, &$exits): int {
            $ran[] = 'migrate:fresh'.($this->option('force') ? ' --force' : '');

            return $exits['migrate:fresh'] ?? 0;
        }));
        $kernel->registerCommand(new ClosureCommand('atrium:install {--demo} {--name=} {--email=} {--password=}', function () use (&$ran, &$installed, &$exits): int {
            $ran[] = 'atrium:install';
            $installed = $this->options();

            if (($exits['atrium:install'] ?? 0) === 0) {
                // What the real installer does: the first administrator is a superadmin.
                AdminUser::query()->create(['name' => $this->option('name'), 'email' => $this->option('email'), 'password' => $this->option('password'), 'is_active' => true])
                    ->forceFill(['is_superadmin' => true])->save();
            }

            return $exits['atrium:install'] ?? 0;
        }));
    }

    private function demo(): void
    {
        config(['atrium.demo.enabled' => true, 'atrium.demo.email' => 'visitor@demo.test', 'atrium.demo.password' => 'demo']);
    }

    public function test_a_site_that_is_not_a_demo_is_left_alone(): void
    {
        $this->admin();
        Storage::disk('public')->put('photo.jpg', 'x');

        $this->artisan('atrium:demo:reset')
            ->expectsOutput('This is not a demo site: set ATRIUM_DEMO=true to use this command. Nothing was changed.')
            ->assertFailed();

        $this->assertSame([], $this->ran);
        $this->assertSame(1, AdminUser::query()->count());
        Storage::disk('public')->assertExists('photo.jpg');
    }

    public function test_the_demo_is_emptied_filled_again_and_given_its_visitor(): void
    {
        $this->demo();
        Storage::disk('public')->put('.gitignore', "*\n");
        Storage::disk('public')->put('photo.jpg', 'x');
        Storage::disk('public')->put('uploads/2026/cat.png', 'x');

        $this->artisan('atrium:demo:reset')
            ->expectsOutputToContain('The demo is back as it was: visitor@demo.test.')
            ->assertSuccessful();

        $this->assertSame(['migrate:fresh --force', 'atrium:install'], $this->ran);
        $this->assertTrue($this->installed['demo']);
        $this->assertSame('Demo', $this->installed['name']);
        $this->assertSame('visitor@demo.test', $this->installed['email']);
        $this->assertSame(40, strlen($this->installed['password']));
        $this->assertSame(['.gitignore'], Storage::disk('public')->allFiles());
        Storage::disk('public')->assertExists('.gitignore');
        Storage::disk('public')->assertMissing('photo.jpg');
        Storage::disk('public')->assertMissing('uploads/2026/cat.png');
        $this->assertSame([], Storage::disk('public')->directories());

        $visitor = AdminUser::query()->sole();
        $this->assertFalse($visitor->isSuperadmin());
        $this->assertTrue(Hash::check('demo', $visitor->password));
        $this->assertFalse(Hash::check($this->installed['password'], $visitor->password));
        $this->assertSame(['Demo'], $visitor->roles->pluck('name')->all());
        $this->assertSame(1, AdminRole::query()->count());
    }

    public function test_the_visitor_edits_the_content_and_only_looks_at_the_rest(): void
    {
        $this->demo();
        config(['atrium.demo.read_only' => ['posts']]);

        $this->artisan('atrium:demo:reset')->assertSuccessful();

        $granted = AdminUser::query()->sole()->adminPermissions();
        $all = app(Permissions::class)->all();

        $this->assertTrue(array_is_list(AdminRole::query()->sole()->permissions));
        $this->assertContains('posts.view', $granted);
        $this->assertNotContains('posts.create', $granted);
        $this->assertNotContains('posts.update', $granted);
        $this->assertNotContains('posts.delete', $granted);
        $this->assertContains('sites.view', $granted);
        $this->assertContains('sites.update', $granted);
        $this->assertContains('users.delete', $granted);
        $this->assertNotContains('api-tokens.use', $granted);
        $this->assertSame([], array_filter($granted, static fn (string $permission): bool => str_ends_with($permission, '.settings')));
        // Bringing records in from a file (it may fetch pictures from any address) and taking them out is not the visitor's.
        $transfer = array_values(array_filter($all, static fn (string $permission): bool => str_ends_with($permission, '.import') || str_ends_with($permission, '.export')));
        $this->assertContains('posts.import', $transfer);
        $this->assertContains('posts.export', $transfer);
        $this->assertContains('languages.export', $transfer);
        $this->assertSame([], array_intersect($transfer, $granted));
        $this->assertEqualsCanonicalizing(
            array_values(array_diff($all, ['posts.create', 'posts.update', 'posts.delete', 'api-tokens.use'], $transfer)),
            $granted,
        );
    }

    public function test_the_sections_a_demo_only_shows_are_in_the_config_by_default(): void
    {
        $this->assertSame(
            ['users', 'languages', 'sites', 'uptime_checks', 'monitoring_incidents'],
            (require dirname(__DIR__, 2).'/config/atrium.php')['demo']['read_only'],
        );
    }

    public function test_the_reset_stops_where_the_database_could_not_be_emptied(): void
    {
        $this->demo();
        $this->exits['migrate:fresh'] = 1;

        $this->artisan('atrium:demo:reset')->assertFailed();

        $this->assertSame(['migrate:fresh --force'], $this->ran);
        $this->assertSame(0, AdminUser::query()->count());
        $this->assertSame(0, AdminRole::query()->count());
    }

    public function test_the_reset_stops_where_the_installer_failed(): void
    {
        $this->demo();
        $this->exits['atrium:install'] = 1;

        $this->artisan('atrium:demo:reset')->assertFailed();

        $this->assertSame(['migrate:fresh --force', 'atrium:install'], $this->ran);
        $this->assertSame(0, AdminRole::query()->count());
    }

    public function test_the_visitor_cannot_change_their_own_name_e_mail_or_password(): void
    {
        $this->demo();
        $this->artisan('atrium:demo:reset')->assertSuccessful();
        $visitor = AdminUser::query()->sole();

        $this->actingAs($visitor, 'admin')
            ->put('/admin/profile', ['name' => 'Mallory', 'email' => 'mallory@example.com'])
            ->assertForbidden();
        $this->actingAs($visitor, 'admin')
            ->put('/admin/profile/password', ['current_password' => 'demo', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertForbidden();

        $visitor->refresh();
        $this->assertSame('Demo', $visitor->name);
        $this->assertSame('visitor@demo.test', $visitor->email);
        $this->assertTrue(Hash::check('demo', $visitor->password));
        $this->actingAs($visitor, 'admin')->get('/admin/profile')->assertOk();
    }

    public function test_the_profile_is_locked_for_a_superadmin_of_a_demo_too(): void
    {
        $this->demo();

        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/profile', ['name' => 'Mallory', 'email' => 'mallory@example.com'])
            ->assertForbidden();
    }

    public function test_the_visitor_has_no_api_tokens_roles_or_cache_screens(): void
    {
        $this->demo();
        $this->artisan('atrium:demo:reset')->assertSuccessful();
        $visitor = AdminUser::query()->sole();

        $this->actingAs($visitor, 'admin');
        $this->get('/admin/api-tokens')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
        $this->get('/admin/cache')->assertForbidden();
        $this->get('/admin/modules')->assertForbidden();
    }

    public function test_the_profile_of_a_site_that_is_not_a_demo_is_not_locked(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/profile', ['name' => 'Ann', 'email' => 'ann@example.com'])
            ->assertRedirect();

        $this->assertSame('Ann', AdminUser::query()->sole()->name);
    }

    public function test_the_sign_in_page_of_a_demo_shows_the_visitors_e_mail_and_password(): void
    {
        $this->demo();
        config(['atrium.demo.password' => 'try-me-1']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Auth/Login')
                ->where('demo', ['email' => 'visitor@demo.test', 'password' => 'try-me-1']));
    }

    public function test_the_details_the_sign_in_page_shows_let_the_visitor_in(): void
    {
        $this->demo();
        $this->artisan('atrium:demo:reset')->assertSuccessful();

        $shown = $this->get('/admin/login')->viewData('page')['props']['demo'];

        $this->post('/admin/login', $shown)->assertRedirect('/admin');
        $this->assertAuthenticatedAs(AdminUser::query()->sole(), 'admin');
    }

    public function test_the_sign_in_page_of_a_site_that_is_not_a_demo_shows_no_details(): void
    {
        config(['atrium.demo.email' => 'visitor@demo.test', 'atrium.demo.password' => 'demo']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('demo', null));
    }

    public function test_nobody_changes_or_deletes_the_demo_account_through_the_list_of_administrators(): void
    {
        $this->demo();
        // A role that may do everything with administrators: the lock does not depend on it.
        config(['atrium.demo.read_only' => []]);
        $this->artisan('atrium:demo:reset')->assertSuccessful();
        $visitor = AdminUser::query()->sole();
        $other = AdminUser::query()->create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $this->actingAs($visitor, 'admin');

        $this->put("/admin/users/{$visitor->id}", ['name' => 'Mallory', 'email' => 'mallory@example.com', 'password' => 'new-password-1'])->assertForbidden();
        $this->delete("/admin/users/{$visitor->id}")->assertForbidden();

        $visitor->refresh();
        $this->assertSame('Demo', $visitor->name);
        $this->assertTrue(Hash::check('demo', $visitor->password));

        // Other accounts are not part of the demo's visitor: the lock is theirs alone.
        $this->put("/admin/users/{$other->id}", ['name' => 'Olga', 'email' => 'other@example.com'])->assertRedirect();
        $this->assertSame('Olga', $other->fresh()->name);
        $this->delete("/admin/users/{$other->id}")->assertRedirect();
        $this->assertNull(AdminUser::query()->find($other->id));
    }

    public function test_the_account_of_a_site_that_is_not_a_demo_is_edited_as_usual(): void
    {
        $user = $this->admin();
        config(['atrium.demo.email' => $user->email]);
        $this->actingAs($user, 'admin');

        $this->put("/admin/users/{$user->id}", ['name' => 'Ada', 'email' => $user->email])->assertRedirect();
        $this->assertSame('Ada', $user->fresh()->name);
    }

    public function test_no_module_settings_are_saved_in_a_demo_not_even_by_a_superadmin(): void
    {
        $this->demo();

        $this->actingAs($this->admin(), 'admin')->put('/admin/modules/anything/settings', ['x' => 'y'])->assertForbidden();
    }

    public function test_module_settings_are_not_locked_on_a_site_that_is_not_a_demo(): void
    {
        $this->actingAs($this->admin(), 'admin')->put('/admin/modules/anything/settings', ['x' => 'y'])->assertNotFound();
    }
}
