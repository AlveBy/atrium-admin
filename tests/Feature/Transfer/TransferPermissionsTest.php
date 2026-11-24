<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Http\Controllers\ResourceController;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Concerns\CreatesEditors;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Transfer\NoExportShelf;
use Atrium\Admin\Tests\Fixtures\Transfer\NoImportShelf;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

/**
 * Import and export as permissions: offered to a role only where the module has
 * the routes, checked on every route, and shown on the list as two buttons.
 */
final class TransferPermissionsTest extends TransferTestCase
{
    use CreatesEditors;

    /** @var array<string,AdminUser> */
    private array $users = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Parcel::class, Post::class, NoImportShelf::class, NoExportShelf::class]);
    }

    /**
     * @return array<string,array{string,array<int,string>}> module, the actions of its row
     */
    public static function rows(): array
    {
        $plain = ['view', 'create', 'update', 'delete'];
        // Content goes to the trash; the languages, a service module, do not.
        $trash = [...$plain, 'restore', 'force_delete'];

        return [
            'a module that takes part in both' => ['parcels', [...$trash, 'import', 'export']],
            'another one' => ['posts', [...$trash, 'import', 'export']],
            'one that opts out of the import' => ['no_import_shelves', [...$trash, 'export']],
            'one that opts out of the export' => ['no_export_shelves', [...$trash, 'import']],
            'the sites, which cannot be imported' => ['sites', [...$trash, 'export']],
            'the languages, the same' => ['languages', [...$plain, 'export']],
        ];
    }

    /**
     * @param  array<int,string>  $actions
     */
    #[DataProvider('rows')]
    public function test_a_row_of_the_role_form_has_import_and_export_where_the_routes_are(string $module, array $actions): void
    {
        $row = collect(app(Permissions::class)->sections())->firstWhere('key', $module);

        $this->assertSame($actions, $row['actions']);
    }

    public function test_every_permission_that_exists_lists_the_transfer_ones_of_the_modules_that_have_them(): void
    {
        $all = app(Permissions::class)->all();

        foreach (['parcels.import', 'parcels.export', 'posts.import', 'posts.export', 'no_import_shelves.export', 'no_export_shelves.import', 'sites.export', 'languages.export'] as $permission) {
            $this->assertContains($permission, $all);
        }

        foreach (['no_import_shelves.import', 'no_export_shelves.export', 'sites.import', 'languages.import'] as $permission) {
            $this->assertNotContains($permission, $all);
        }
    }

    public function test_the_api_tokens_row_is_still_the_last_one_and_has_no_transfer(): void
    {
        $sections = app(Permissions::class)->sections();

        $this->assertSame(['key' => 'api-tokens', 'label' => 'API tokens', 'actions' => ['use']], end($sections));
    }

    /**
     * @return array<string,array{string,string}> route, the permission it needs
     */
    public static function routes(): array
    {
        return [
            'the import list' => ['admin.parcels.import', 'parcels.import'],
            'the upload' => ['admin.parcels.import.store', 'parcels.import'],
            'the mapping screen' => ['admin.parcels.import.show', 'parcels.import'],
            'the check' => ['admin.parcels.import.check', 'parcels.import'],
            'the import' => ['admin.parcels.import.run', 'parcels.import'],
            'the progress' => ['admin.parcels.import.step', 'parcels.import'],
            'the stop' => ['admin.parcels.import.cancel', 'parcels.import'],
            'the export' => ['admin.parcels.export', 'parcels.export'],
            'a module with an underscore' => ['admin.no_export_shelves.import', 'no_export_shelves.import'],
            'a module with an underscore, exported' => ['admin.no_import_shelves.export', 'no_import_shelves.export'],
        ];
    }

    #[DataProvider('routes')]
    public function test_a_route_needs_its_permission_and_no_other(string $route, string $permission): void
    {
        $permissions = app(Permissions::class);
        $others = array_values(array_diff(['parcels.import', 'parcels.export', 'no_export_shelves.import', 'no_import_shelves.export', 'parcels.view', 'parcels.create', 'parcels.update', 'parcels.delete'], [$permission]));

        $this->assertTrue($permissions->allowsRoute($this->editor([$permission], 'has@example.com'), $route));
        $this->assertFalse($permissions->allowsRoute($this->editor($others, 'lacks@example.com'), $route));
        $this->assertFalse($permissions->allowsRoute($this->editor([], 'none@example.com'), $route));
        $this->assertTrue($permissions->allowsRoute($this->admin(), $route));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function open(): array
    {
        return [
            'a module that is not there' => ['admin.nothing.import'],
            'an export of a module that is not there' => ['admin.nothing.export'],
            'a longer name than the screens have' => ['admin.parcels.imports'],
            'a step of two names' => ['admin.parcels.import.run.again'],
            'a step in capitals' => ['admin.parcels.import.Run'],
            'a step with a digit' => ['admin.parcels.import.run2'],
            'something that is not the panel' => ['site.parcels.import'],
        ];
    }

    #[DataProvider('open')]
    public function test_a_route_that_is_not_one_of_the_screens_needs_no_import_permission(string $route): void
    {
        $this->assertTrue(app(Permissions::class)->allowsRoute($this->editor([], 'none@example.com'), $route));
    }

    // The buttons on the list

    /**
     * @param  array<int,string>|null  $permissions  null for a superadmin
     * @return array<string,bool>
     */
    private function transfer(string $module, ?array $permissions): array
    {
        $key = $permissions === null ? 'admin' : implode(',', $permissions);
        $this->users[$key] ??= $permissions === null ? $this->admin() : $this->editor($permissions, count($this->users).'@example.com');
        $user = $this->users[$key];
        $transfer = null;
        $this->actingAs($user, 'admin')->get("/admin/{$module}")->assertOk()->assertInertia(function (AssertableInertia $page) use (&$transfer): void {
            $transfer = $page->toArray()['props']['transfer'];
        });

        return $transfer;
    }

    public function test_a_superadmin_has_both_buttons_where_the_module_has_both(): void
    {
        $this->assertSame(['import' => true, 'export' => true], $this->transfer('parcels', null));
    }

    public function test_a_role_without_the_permissions_has_neither_button(): void
    {
        $this->assertSame(['import' => false, 'export' => false], $this->transfer('parcels', ['parcels.view']));
    }

    public function test_each_button_follows_its_own_permission(): void
    {
        $this->assertSame(['import' => false, 'export' => true], $this->transfer('parcels', ['parcels.view', 'parcels.export']));
        $this->assertSame(['import' => true, 'export' => false], $this->transfer('parcels', ['parcels.view', 'parcels.import']));
    }

    public function test_the_permission_of_another_module_gives_no_button(): void
    {
        $this->assertSame(['import' => false, 'export' => false], $this->transfer('parcels', ['parcels.view', 'posts.import', 'posts.export']));
    }

    public function test_a_module_that_opts_out_has_no_button_for_it_even_for_a_superadmin(): void
    {
        $this->assertSame(['import' => false, 'export' => true], $this->transfer('no_import_shelves', null));
        $this->assertSame(['import' => true, 'export' => false], $this->transfer('no_export_shelves', null));
        $this->assertSame(['import' => false, 'export' => true], $this->transfer('sites', null));
    }

    public function test_an_opted_out_module_has_no_button_even_for_a_role_that_holds_the_permission(): void
    {
        $this->assertSame(['import' => false, 'export' => true], $this->transfer('no_import_shelves', ['no_import_shelves.view', 'no_import_shelves.import', 'no_import_shelves.export']));
    }

    public function test_without_an_administrator_the_routes_alone_decide(): void
    {
        $controller = app(ResourceController::class);
        $model = new \ReflectionProperty($controller, 'model');
        $model->setValue($controller, NoImportShelf::class);

        $this->assertSame(['import' => false, 'export' => true], (new ReflectionMethod($controller, 'transferAbilities'))->invoke($controller));

        $model->setValue($controller, Parcel::class);

        $this->assertSame(['import' => true, 'export' => true], (new ReflectionMethod($controller, 'transferAbilities'))->invoke($controller));
    }
}
