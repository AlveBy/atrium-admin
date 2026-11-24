<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Extensions\Panel;
use Atrium\Admin\Extensions\Panels;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;

/**
 * Blocks other modules draw on a record's page: for one module or all,
 * under the form, beside it or on a tab, for those allowed to see them.
 */
final class RecordPanelsTest extends TestCase
{
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['view']->addNamespace('acme', dirname(__DIR__).'/Fixtures/views');
        $this->post = Post::query()->create(['name' => '<Launch>']);
    }

    /**
     * @return list<array<string,string>>
     */
    private function panelsOnThePost(AdminUser $user): array
    {
        $panels = [];

        $this->actingAs($user, 'admin')->get("/admin/posts/{$this->post->id}/edit")->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$panels): void {
                $panels = $page->toArray()['props']['panels'];
            });

        return $panels;
    }

    /**
     * @param  list<array<string,string>>  $panels
     * @return list<string>
     */
    private function keys(array $panels): array
    {
        return array_column($panels, 'key');
    }

    private function editor(array $permissions): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Editors', 'permissions' => $permissions]));

        return $user->fresh();
    }

    public function test_a_panel_draws_its_view_with_the_record_and_the_administrator(): void
    {
        Hook::register('atrium.ui.panels', 'acme.time', Panel::make('acme.time')->for(Post::class)->label('Time')->html('acme::panel'));

        $this->assertSame([
            ['key' => 'acme.time', 'label' => 'Time', 'placement' => 'below', 'html' => "<p>&lt;Launch&gt; for Admin</p>\n"],
        ], $this->panelsOnThePost($this->admin()));
    }

    public function test_placements_keep_the_order_of_the_registry(): void
    {
        Hook::register('atrium.ui.panels', 'acme.tab', Panel::make('acme.tab')->html('acme::panel')->tab(), 20);
        Hook::register('atrium.ui.panels', 'acme.side', Panel::make('acme.side')->html('acme::panel')->side());
        Hook::register('atrium.ui.panels', 'acme.below', Panel::make('acme.below')->html('acme::panel')->side()->below());

        $panels = $this->panelsOnThePost($this->admin());

        $this->assertSame(['acme.side', 'acme.below', 'acme.tab'], $this->keys($panels));
        $this->assertSame(['side', 'below', 'tab'], array_column($panels, 'placement'));
        $this->assertSame(['acme.side', 'acme.below', 'acme.tab'], array_column($panels, 'label'));
    }

    public function test_a_panel_for_another_module_is_not_on_this_one_and_one_for_none_is_on_all(): void
    {
        Hook::register('atrium.ui.panels', 'acme.languages', Panel::make('acme.languages')->for(Language::class)->html('acme::panel'));
        Hook::register('atrium.ui.panels', 'acme.everywhere', Panel::make('acme.everywhere')->html('acme::panel'));

        $this->assertSame(['acme.everywhere'], $this->keys($this->panelsOnThePost($this->admin())));
        $this->assertSame(['acme.languages', 'acme.everywhere'], array_column(app(Panels::class)->for(new Language, null), 'key'));
    }

    public function test_a_label_is_translated(): void
    {
        $this->app->setLocale('ru');

        $this->assertSame('Сохранить', Panel::make('acme.saved')->label('atrium::common.save')->labelText());
        $this->assertSame('acme.raw', Panel::make('acme.raw')->labelText());
    }

    public function test_a_panel_with_a_permission_is_only_for_those_who_have_it(): void
    {
        Hook::register('atrium.ui.panels', 'acme.secret', Panel::make('acme.secret')->html('acme::panel')->permission('acme.time'));
        Hook::register('atrium.ui.panels', 'acme.open', Panel::make('acme.open')->html('acme::panel'));

        $this->assertSame(['acme.secret', 'acme.open'], $this->keys($this->panelsOnThePost($this->admin())));
        $this->assertSame(['acme.open'], $this->keys($this->panelsOnThePost($this->editor(['posts.view', 'posts.update']))));
        $this->assertSame(['acme.open'], array_column(app(Panels::class)->for($this->post, null), 'key'));
    }

    public function test_a_permission_given_by_a_role_shows_the_panel(): void
    {
        Hook::register('atrium.ui.panels', 'acme.secret', Panel::make('acme.secret')->html('acme::panel')->permission('acme.time'));

        $panels = $this->panelsOnThePost($this->editor(['posts.view', 'posts.update', 'acme.time']));

        $this->assertSame(['acme.secret'], $this->keys($panels));
        $this->assertSame("<p>&lt;Launch&gt; for Editor</p>\n", $panels[0]['html']);
    }

    public function test_a_condition_gets_the_record_and_the_administrator_and_must_say_true(): void
    {
        $seen = [];
        Hook::register('atrium.ui.panels', 'acme.launches', Panel::make('acme.launches')->html('acme::panel')
            ->when(static function (Model $record, ?AdminUser $user) use (&$seen): bool {
                $seen[] = [$record, $user];

                return $record->getAttribute('name') === '<Launch>';
            }));
        Hook::register('atrium.ui.panels', 'acme.truthy', Panel::make('acme.truthy')->html('acme::panel')->when(static fn (): int => 1));
        $admin = $this->admin();

        $this->assertSame(['acme.launches'], $this->keys($this->panelsOnThePost($admin)));
        $this->assertTrue($this->post->is($seen[0][0]));
        $this->assertTrue($admin->is($seen[0][1]));

        $this->post->update(['name' => 'Other']);
        $this->assertSame([], $this->panelsOnThePost($admin));
    }

    public function test_the_registry_hears_the_record_and_may_drop_a_panel_for_it(): void
    {
        Hook::register('atrium.ui.panels', 'acme.time', Panel::make('acme.time')->html('acme::panel'));
        Hook::filter('atrium.ui.panels', static fn (array $panels, Model $record): array => $record->getAttribute('name') === 'Hidden' ? [] : $panels, 20);

        $this->assertSame(['acme.time'], $this->keys($this->panelsOnThePost($this->admin())));
        $this->post->update(['name' => 'Hidden']);
        $this->assertSame([], app(Panels::class)->for($this->post, null));
    }

    public function test_what_has_no_view_or_is_not_a_panel_is_left_out_and_a_broken_one_is_reported(): void
    {
        Exceptions::fake();
        Hook::register('atrium.ui.panels', 'acme.empty', Panel::make('acme.empty')->label('Nothing to draw'));
        Hook::register('atrium.ui.panels', 'acme.text', 'not a panel');
        Hook::register('atrium.ui.panels', 'acme.broken', Panel::make('acme.broken')->html('acme::no-such-view'));
        Hook::register('atrium.ui.panels', 'acme.time', Panel::make('acme.time')->html('acme::panel'));

        $this->assertSame(['acme.time'], $this->keys($this->panelsOnThePost($this->admin())));
        Exceptions::assertReported(InvalidArgumentException::class);
        Exceptions::assertReportedCount(1);
    }

    public function test_a_record_without_panels_has_an_empty_list(): void
    {
        $this->assertSame([], $this->panelsOnThePost($this->admin()));
    }
}
