<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\TestCase;

final class ThemeTest extends TestCase
{
    use PinsExtensionSurface;

    public function test_the_page_is_rendered_in_the_configured_theme(): void
    {
        config(['atrium.admin.theme' => 'sand']);

        $response = $this->get('/admin/login');

        $response->assertSee('data-theme="sand"', false);
        $this->assertSame('sand', $response->inertiaProps('theme'));
        $this->assertSame(['system', 'graphite', 'day', 'night', 'sand', 'contrast'], $response->inertiaProps('themes'));
    }

    public function test_an_unknown_configured_theme_falls_back_to_graphite(): void
    {
        config(['atrium.admin.theme' => 'purple']);

        $this->get('/admin/login')->assertSee('data-theme="graphite"', false);
    }

    public function test_an_admin_keeps_the_theme_on_the_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->from('/admin')
            ->put('/admin/profile/theme', ['theme' => 'night'])
            ->assertRedirect('/admin');

        $this->assertSame('night', $admin->fresh()->theme);

        $response = $this->actingAs($admin->fresh(), 'admin')->get('/admin');
        $response->assertSee('data-theme="night"', false);
        $this->assertSame('night', $response->inertiaProps('theme'));
    }

    public function test_as_the_system_renders_graphite_and_leaves_the_choice_to_the_head_script(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['theme' => 'system'])->save();

        $this->actingAs($admin, 'admin')->get('/admin')
            ->assertSee('data-theme="graphite" data-theme-choice="system"', false)
            ->assertSee('prefers-color-scheme: dark', false);
    }

    public function test_an_unknown_theme_is_refused(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put('/admin/profile/theme', ['theme' => 'purple'])
            ->assertSessionHasErrors('theme');

        $this->assertNull($admin->fresh()->theme);
    }

    public function test_a_theme_is_required(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/profile/theme', [])
            ->assertSessionHasErrors('theme');
    }

    public function test_a_guest_cannot_save_a_theme(): void
    {
        $this->put('/admin/profile/theme', ['theme' => 'night'])->assertRedirect('/admin/login');
    }

    public function test_the_atrium_favicon_is_used_until_the_app_sets_its_own(): void
    {
        $this->get('/admin/login')
            ->assertSee('vendor/atrium/favicon.svg', false)
            ->assertSee('vendor/atrium/apple-touch-icon.png', false);

        config(['atrium.branding.favicon' => '/my-icon.png']);

        $this->get('/admin/login')
            ->assertSee('/my-icon.png', false)
            ->assertDontSee('vendor/atrium/favicon.svg', false);
    }
}
