<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\TestCase;

/**
 * What every panel page is given besides its own props, in exact form.
 */
final class SharedPropsExactTest extends TestCase
{
    public function test_the_signed_in_administrator_is_shared_with_id_name_email_and_rights(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin, 'admin')->get('/admin/posts')->assertOk();

        $this->assertSame(
            ['user' => ['id' => $admin->id, 'name' => 'Admin', 'email' => 'admin@example.com', 'is_superadmin' => true]],
            $response->inertiaProps('auth'),
        );

        $plain = AdminUser::query()->create(['name' => 'Plain', 'email' => 'plain@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $this->assertSame(
            ['user' => ['id' => $plain->id, 'name' => 'Plain', 'email' => 'plain@example.com', 'is_superadmin' => false]],
            $this->actingAs($plain, 'admin')->get('/admin/profile')->inertiaProps('auth'),
        );
    }

    public function test_nobody_is_shared_on_the_sign_in_page(): void
    {
        $this->assertSame(['user' => null], $this->get('/admin/login')->inertiaProps('auth'));
    }

    public function test_the_site_and_the_site_switch_carry_ids_and_names(): void
    {
        $main = Site::query()->where('code', 'main')->sole();
        $other = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de']]);
        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/posts');

        $this->assertSame([['id' => $main->id, 'name' => $main->name], ['id' => $other->id, 'name' => 'Deutschland']], $response->inertiaProps('sites'));
        $this->assertSame(['id' => $main->id, 'name' => $main->name, 'url' => $main->url()], $response->inertiaProps('currentSite'));
    }

    public function test_every_kind_of_flash_message_is_shared(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin, 'admin')
            ->withSession(['success' => 'Done', 'error' => 'Broken', 'warning' => 'Careful', 'info' => 'FYI'])
            ->get('/admin/posts');

        $this->assertSame(['success' => 'Done', 'error' => 'Broken', 'warning' => 'Careful', 'info' => 'FYI'], $response->inertiaProps('flash'));

        $this->flushSession();
        $this->assertSame(['success' => null, 'error' => null, 'warning' => null, 'info' => null], $this->actingAs($admin, 'admin')->get('/admin/posts')->inertiaProps('flash'));
    }

    public function test_the_dictionary_is_sent_once_per_language_under_its_own_name(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/posts', ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);

        $this->assertSame(['atrium.translations.en'], array_keys((array) $response->json('onceProps')));
    }

    public function test_the_asset_version_follows_the_manifest(): void
    {
        $file = public_path('vendor/atrium/manifest.json');
        @mkdir(dirname($file), 0777, true);
        $existed = is_file($file);
        $backup = $existed ? (string) file_get_contents($file) : null;
        file_put_contents($file, '{"a":1}');

        try {
            $this->assertSame(md5('{"a":1}'), $this->get('/admin/login')->viewData('page')['version']);
        } finally {
            $backup === null ? unlink($file) : file_put_contents($file, $backup);
        }
    }
}
