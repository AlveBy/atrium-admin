<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Support\Menu;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

/**
 * Actions and filters: what modules hang on each other and on the core.
 */
final class HooksTest extends TestCase
{
    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(Modules::class, static fn (Modules $modules) => $modules->add(Label::module()));

        $this->root = sys_get_temp_dir().'/atrium-hooks-'.uniqid();
        mkdir($this->root.'/config', 0777, true);
        copy(dirname(__DIR__, 2).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_an_action_reaches_every_listener_with_its_arguments(): void
    {
        $heard = [];
        Hook::on('acme.order.paid', function (int $order, string $by) use (&$heard): void {
            $heard[] = "first {$order} {$by}";
        });
        Hook::on('acme.order.paid', function (int $order) use (&$heard): void {
            $heard[] = "second {$order}";
        });

        Hook::fire('acme.order.paid', 7, 'card');
        Hook::fire('acme.nobody.listens', 1);

        $this->assertSame(['first 7 card', 'second 7'], $heard);
        $this->assertTrue(Hook::has('acme.order.paid'));
        $this->assertFalse(Hook::has('acme.nobody.listens'));
    }

    public function test_filters_run_in_order_of_priority_and_pass_the_value_on(): void
    {
        Hook::filter('acme.sources', fn (array $sources): array => [...$sources, 'late'], 20);
        Hook::filter('acme.sources', fn (array $sources, string $site): array => [...$sources, "default {$site}"]);
        Hook::filter('acme.sources', fn (array $sources): array => ['early', ...$sources], 5);
        Hook::filter('acme.sources', UppercaseSources::class, 30);

        $this->assertSame(['EARLY', 'CORE', 'DEFAULT MAIN', 'LATE'], Hook::apply('acme.sources', ['core'], 'main'));
        $this->assertSame(['untouched'], Hook::apply('acme.nobody.filters', ['untouched']));
        $this->assertTrue(Hook::has('acme.sources'));
    }

    public function test_arguments_given_by_name_reach_the_listeners_and_filters_by_position(): void
    {
        $heard = [];
        Hook::on('acme.named', function (int $first, string $second) use (&$heard): void {
            $heard[] = "{$first} {$second}";
        });
        Hook::filter('acme.named.filter', fn (string $value, string $extra): string => $value.$extra);

        Hook::fire('acme.named', order: 7, by: 'card');

        $this->assertSame(['7 card'], $heard);
        $this->assertSame('ab', Hook::apply('acme.named.filter', 'a', suffix: 'b'));
    }

    public function test_a_filter_without_a_priority_runs_at_ten_between_nine_and_eleven(): void
    {
        Hook::filter('acme.order', fn (string $value): string => $value.'-eleven', 11);
        Hook::filter('acme.order', fn (string $value): string => $value.'-default');
        Hook::filter('acme.order', fn (string $value): string => $value.'-nine', 9);

        $this->assertSame('start-nine-default-eleven', Hook::apply('acme.order', 'start'));
    }

    public function test_a_filter_added_later_joins_the_ones_that_already_ran(): void
    {
        Hook::filter('acme.later', fn (string $value): string => $value.'-first');
        $this->assertSame('x-first', Hook::apply('acme.later', 'x'));

        Hook::filter('acme.later', fn (string $value): string => $value.'-second');
        $this->assertSame('x-first-second', Hook::apply('acme.later', 'x'));
    }

    public function test_a_filter_may_be_the_name_of_a_function(): void
    {
        Hook::filter('acme.function', 'strtoupper');

        $this->assertSame('ABC', Hook::apply('acme.function', 'abc'));
    }

    public function test_saving_and_deleting_a_record_in_the_panel_fires_the_core_hooks(): void
    {
        $saved = [];
        $deleted = [];
        Hook::on('atrium.record.saved', function (Post $post, string $context) use (&$saved): void {
            $saved[] = "{$context} {$post->name}";
        });
        Hook::on('atrium.record.deleted', function (Post $post) use (&$deleted): void {
            $deleted[] = $post->name;
        });
        Hook::filter('atrium.record.data', fn (array $data, Post $post, string $context): array => [...$data, 'name' => trim($data['name']).($context === 'create' ? ' (new)' : '')]);

        $this->actingAs($this->admin(), 'admin')->post('/admin/posts', ['name' => ' First ', 'active' => true, 'sort' => 10]);
        $post = Post::query()->sole();
        $this->put("/admin/posts/{$post->id}", ['name' => 'Renamed', 'active' => true, 'sort' => 10]);
        $this->delete("/admin/posts/{$post->id}");

        $this->assertSame(['create First (new)', 'edit Renamed'], $saved);
        $this->assertSame(['Renamed'], $deleted);
    }

    public function test_a_module_adds_to_the_admin_menu_and_permissions_still_apply(): void
    {
        Hook::filter('atrium.admin.menu', fn (array $items, ?AdminUser $user): array => [
            ...$items,
            ['label' => 'Reports', 'route' => 'admin.posts.index', 'icon' => 'chart'],
        ]);

        $this->assertContains('Reports', array_column($this->app->make(Menu::class)->items($this->admin()), 'label'));

        $nobody = AdminUser::query()->create(['name' => 'Nobody', 'email' => 'nobody@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $nobody->roles()->attach(AdminRole::query()->create(['name' => 'Empty', 'permissions' => []]));

        $this->assertNotContains('Reports', array_column($this->app->make(Menu::class)->items($nobody->fresh()), 'label'));
    }

    public function test_installing_and_removing_a_module_fire_their_actions(): void
    {
        $heard = [];
        Hook::on('atrium.module.installed', function ($module) use (&$heard): void {
            $heard[] = 'installed '.$module->name;
        });
        Hook::on('atrium.module.removed', function (string $name, bool $dataDropped) use (&$heard): void {
            $heard[] = "removed {$name} ".($dataDropped ? 'with its data' : 'keeping its data');
        });

        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels', '--delete-data' => true])->assertSuccessful();

        $this->assertSame(['installed acme/atrium-labels', 'removed acme/atrium-labels with its data'], $heard);
    }
}

/**
 * A filter given by its class name.
 */
final class UppercaseSources
{
    /**
     * @param  array<int,string>  $sources
     * @return array<int,string>
     */
    public function __invoke(array $sources): array
    {
        return array_map(strtoupper(...), $sources);
    }
}
