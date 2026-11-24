<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\Activity;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Inertia\Testing\AssertableInertia;

/**
 * A site that keeps no activity log: no journal, no "History" buttons, no
 * entries.
 */
final class HistoryDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.history.enabled', false);
    }

    public function test_there_is_no_journal_and_no_history_of_a_record(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->post('/admin/posts', ['name' => 'Apple'])->assertSessionHasNoErrors();
        $post = Post::query()->sole();

        $this->get('/admin/activity')->assertNotFound();
        $this->get("/admin/posts/{$post->id}/history")->assertNotFound();
        $this->get("/admin/users/{$admin->id}/history")->assertNotFound();
        $this->get("/admin/posts/{$post->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page
            ->where('historyUrl', null)
            ->where('menu', fn ($menu): bool => ! collect($menu)->contains('route', 'admin.activity.index')));
        $this->assertSame(0, Activity::query()->count());
    }
}
