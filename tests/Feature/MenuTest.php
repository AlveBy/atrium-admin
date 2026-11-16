<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\TestCase;
use Inertia\Testing\AssertableInertia;

final class MenuTest extends TestCase
{
    public function test_modules_appear_in_the_menu_without_menu_config(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/posts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('menu.0.route', 'admin.dashboard')
                ->where('menu.1.route', 'admin.posts.index')
                ->where('menu.1.label', 'Posts'));
    }

    public function test_a_module_already_in_the_menu_config_is_not_duplicated(): void
    {
        config(['atrium.menu' => [
            ['label' => 'Content', 'children' => [['label' => 'Blog', 'route' => 'admin.posts.index']]],
        ]]);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/posts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('menu', 2)
                ->where('menu.0.children.0.label', 'Blog')
                // The panel's own module, not in this menu config: added once.
                ->where('menu.1.route', 'admin.languages.index'));
    }

    public function test_the_admin_page_ships_only_admin_routes_to_the_browser(): void
    {
        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('"admin.login"', (string) $html);
        $this->assertStringNotContainsString('"storage.local"', (string) $html);
    }
}
