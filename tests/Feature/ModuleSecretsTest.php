<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Setting;
use Atrium\Admin\Http\Controllers\ModuleController;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Modules\Settings;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\Fixtures\Modules\LabelSettings;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use ReflectionMethod;

/**
 * Settings that hold a secret, and the buttons a module puts on its screen.
 */
final class ModuleSecretsTest extends TestCase
{
    private const URL = 'https://portal.example.test/rest/1/SECRETCODE/';

    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(Modules::class, static function (Modules $modules): void {
            $modules->add(Label::module());
            $modules->add(Module::fromPath(dirname(__DIR__).'/Fixtures/Modules/vault') ?? throw new \RuntimeException('The vault fixture is not a module.'));
        });

        $this->root = sys_get_temp_dir().'/atrium-secrets-'.uniqid();
        mkdir($this->root.'/config', 0777, true);
        copy(dirname(__DIR__, 2).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-vault'])->assertSuccessful();
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    private function stored(): string
    {
        return (string) DB::table(Settings::TABLE)->where('module', 'vault')->value('values');
    }

    public function test_a_secret_is_stored_encrypted_and_read_back_whole(): void
    {
        app(Settings::class)->save('vault', ['name' => 'Main', 'webhook' => self::URL]);

        $stored = $this->stored();
        $this->assertStringNotContainsString('SECRETCODE', $stored);
        $this->assertStringNotContainsString('portal.example.test', $stored);
        $this->assertSame(self::URL, Crypt::decryptString(json_decode($stored, true)['webhook']));
        $this->assertStringContainsString('Main', $stored, 'what is not a secret stays plain');

        app(Settings::class)->flush();
        $this->assertSame(self::URL, Setting::get('vault.webhook'));
        $this->assertSame(['name' => 'Main', 'webhook' => self::URL, 'note' => null, 'token' => 'none'], Setting::all('vault'));
    }

    public function test_saving_other_values_keeps_the_secret_and_saving_it_empty_clears_it(): void
    {
        $settings = app(Settings::class);
        $settings->save('vault', ['webhook' => self::URL]);

        $settings->save('vault', ['name' => 'Second']);
        $settings->flush();
        $this->assertSame(self::URL, Setting::get('vault.webhook'));

        $settings->save('vault', ['webhook' => '']);
        $settings->flush();
        $this->assertNull(Setting::get('vault.webhook'));
        $this->assertSame('Second', Setting::get('vault.name'));
    }

    public function test_a_secret_that_cannot_be_decrypted_reads_as_nothing(): void
    {
        DB::table(Settings::TABLE)->insert(['module' => 'vault', 'values' => json_encode(['name' => 'Main', 'webhook' => 'not-an-encrypted-text']), 'created_at' => now(), 'updated_at' => now()]);

        $this->assertNull(Setting::get('vault.webhook'));
        $this->assertSame('fallback', Setting::get('vault.webhook', 'fallback'));
        $this->assertSame('Main', Setting::get('vault.name'));
    }

    public function test_a_module_without_secrets_stores_everything_as_typed(): void
    {
        app(Settings::class)->save('labels', ['color' => 'blue']);

        $this->assertSame('blue', Setting::get('labels.color'));
        $this->assertStringContainsString('"blue"', (string) DB::table(Settings::TABLE)->where('module', 'labels')->value('values'));
        $this->assertSame([], (new LabelSettings)->secrets());
        $this->assertSame('••••••••', (new LabelSettings)->mask('color', 'blue'));
    }

    public function test_the_screen_never_gets_the_secret_only_its_mask(): void
    {
        $admin = $this->admin();
        app(Settings::class)->save('vault', ['name' => 'Main', 'webhook' => self::URL]);

        $response = $this->actingAs($admin, 'admin')->get('/admin/modules/vault/settings');

        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('values.name', 'Main')
            ->missing('values.webhook')
            ->where('fields.0.placeholder', null)
            ->where('fields.1.name', 'webhook')
            ->where('fields.1.label', 'Webhook')
            ->where('fields.1.placeholder', 'portal.example.test/••••')
            ->where('actions', [['name' => 'ping', 'label' => 'Ping'], ['name' => 'break', 'label' => 'Break']]));
        $this->assertStringNotContainsString('SECRETCODE', $response->getContent());
    }

    public function test_before_a_secret_is_saved_there_is_no_mask(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin/modules/vault/settings')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('fields.1.placeholder', null));
    }

    public function test_an_empty_secret_field_keeps_the_saved_secret_and_a_filled_one_replaces_it(): void
    {
        $admin = $this->admin();
        app(Settings::class)->save('vault', ['webhook' => self::URL]);

        $this->actingAs($admin, 'admin')->put('/admin/modules/vault/settings', ['name' => 'Renamed', 'webhook' => ''])->assertSessionHasNoErrors();
        app(Settings::class)->flush();
        $this->assertSame(self::URL, Setting::get('vault.webhook'));
        $this->assertSame('Renamed', Setting::get('vault.name'));

        $this->actingAs($admin, 'admin')->put('/admin/modules/vault/settings', ['name' => 'Renamed', 'webhook' => '  ']);
        app(Settings::class)->flush();
        $this->assertSame(self::URL, Setting::get('vault.webhook'), 'blanks are empty too');

        $this->actingAs($admin, 'admin')->put('/admin/modules/vault/settings', ['webhook' => 'https://other.example.test/rest/2/NEW/']);
        app(Settings::class)->flush();
        $this->assertSame('https://other.example.test/rest/2/NEW/', Setting::get('vault.webhook'));
    }

    public function test_every_secret_has_its_own_mask(): void
    {
        $admin = $this->admin();
        app(Settings::class)->save('vault', ['webhook' => self::URL, 'token' => 'T0P']);

        $this->actingAs($admin, 'admin')->get('/admin/modules/vault/settings')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('fields.1.placeholder', 'portal.example.test/••••')
            ->where('fields.2.placeholder', null)
            ->where('fields.3.placeholder', 'token ••')
            ->missing('values.token'));
    }

    public function test_an_empty_secret_does_not_stop_the_fields_after_it_from_being_saved(): void
    {
        $admin = $this->admin();
        app(Settings::class)->save('vault', ['webhook' => self::URL, 'token' => 'OLD']);

        $this->actingAs($admin, 'admin')->put('/admin/modules/vault/settings', ['webhook' => '', 'note' => 'After it', 'token' => 'NEW'])->assertSessionHasNoErrors();
        app(Settings::class)->flush();

        $this->assertSame([self::URL, 'After it', 'NEW'], [Setting::get('vault.webhook'), Setting::get('vault.note'), Setting::get('vault.token')]);
    }

    public function test_a_secret_that_cannot_be_read_gives_way_to_its_default(): void
    {
        DB::table(Settings::TABLE)->insert(['module' => 'vault', 'values' => json_encode(['token' => 'not-an-encrypted-text']), 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('none', Setting::get('vault.token'));
    }

    public function test_the_button_is_an_action_of_the_controller_the_router_can_call(): void
    {
        $this->assertTrue((new ReflectionMethod(ModuleController::class, 'action'))->isPublic());
    }

    public function test_a_button_runs_and_says_what_it_did(): void
    {
        $admin = $this->admin();
        app(Settings::class)->save('vault', ['webhook' => self::URL]);

        $this->actingAs($admin, 'admin')->from('/admin/modules/vault/settings')->post('/admin/modules/vault/settings/actions/ping')
            ->assertRedirect('/admin/modules/vault/settings')
            ->assertSessionHas('success', 'Pong from '.self::URL)
            ->assertSessionMissing('error');
    }

    public function test_a_button_that_fails_shows_its_message_as_an_error(): void
    {
        $this->actingAs($this->admin(), 'admin')->from('/admin/modules/vault/settings')->post('/admin/modules/vault/settings/actions/break')
            ->assertRedirect('/admin/modules/vault/settings')
            ->assertSessionHas('error', 'It broke.')
            ->assertSessionMissing('success');
    }

    public function test_only_the_buttons_a_module_declares_exist(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post('/admin/modules/vault/settings/actions/explode')->assertNotFound();
        $this->actingAs($admin, 'admin')->post('/admin/modules/labels/settings/actions/ping')->assertNotFound();
        $this->actingAs($admin, 'admin')->get('/admin/modules/labels/settings')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('actions', []));
    }

    public function test_a_button_follows_the_permission_to_set_the_module_up(): void
    {
        $guest = AdminUser::query()->create(['name' => 'Eve', 'email' => 'eve@example.test', 'password' => 'correct-horse', 'is_active' => true]);

        $this->actingAs($guest, 'admin')->post('/admin/modules/vault/settings/actions/ping')->assertForbidden();
    }
}
