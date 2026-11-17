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
use Atrium\Admin\Tests\Fixtures\Album;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PDOException;

/**
 * A save or a delete that hits a deadlock is tried again, three times in all.
 *
 * Laravel retries only the outermost transaction, so these tests run without
 * the one each test is usually wrapped in, on a database migrated afresh.
 */
final class DeadlockRetryTest extends TestCase
{
    /** @var array<int,string> */
    private array $heard = [];

    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::setUp();

        foreach (['atrium.record.saved', 'atrium.record.deleted'] as $hook) {
            Hook::on($hook, function () use ($hook): void {
                $this->heard[] = $hook;
            });
        }
    }

    public function test_a_save_is_tried_again_after_two_deadlocks(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->deadlock('insert', 'posts', times: 2);

        $this->post('/admin/posts', ['name' => 'Saved'])->assertRedirect();

        $this->assertSame(['Saved'], Post::query()->pluck('name')->all());
        $this->assertSame(['atrium.record.saved'], $this->heard);
    }

    public function test_a_save_gives_up_after_three_deadlocks(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->deadlock('insert', 'posts', times: 3);

        $this->post('/admin/posts', ['name' => 'Lost'])->assertServerError();

        $this->assertSame(0, Post::query()->count());
        $this->assertSame([], $this->heard);
    }

    public function test_an_administrator_is_saved_again_whole_when_the_roles_hit_a_deadlock(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $role = AdminRole::query()->create(['name' => 'Editors', 'permissions' => ['posts.view']]);
        // After the administrator is written: the retry must not take the
        // rolled back row for one that is there.
        $this->deadlock('insert', 'admin_role_user', times: 2);

        $this->post('/admin/users', [
            'name' => 'New', 'email' => 'new@example.com', 'is_active' => true, 'is_superadmin' => true,
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
            'roles' => [$role->id],
        ])->assertRedirect();

        $user = AdminUser::query()->where('email', 'new@example.com')->sole();
        $this->assertTrue($user->isSuperadmin());
        $this->assertSame([$role->id], $user->roles()->pluck('admin_roles.id')->all());
        $this->assertSame(['atrium.record.saved'], $this->heard);
    }

    public function test_an_administrator_is_not_saved_after_three_deadlocks(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $role = AdminRole::query()->create(['name' => 'Editors', 'permissions' => ['posts.view']]);
        $this->deadlock('insert', 'admin_role_user', times: 3);

        $this->post('/admin/users', [
            'name' => 'New', 'email' => 'new@example.com', 'is_active' => true, 'is_superadmin' => true,
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
            'roles' => [$role->id],
        ])->assertServerError();

        $this->assertFalse(AdminUser::query()->where('email', 'new@example.com')->exists());
        $this->assertSame([], $this->heard);
    }

    public function test_a_delete_gives_up_after_three_deadlocks(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $post = Post::query()->create(['name' => 'Kept']);
        $this->deadlock('delete', 'posts', times: 3);

        $this->delete("/admin/posts/{$post->id}")->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);

        $this->assertSame(['Kept'], Post::query()->pluck('name')->all());
        $this->assertSame([], $this->heard);
    }

    public function test_files_are_reordered_after_two_deadlocks_and_not_after_three(): void
    {
        $album = Album::query()->create(['name' => 'Summer']);
        $ids = [];
        foreach (['a', 'b'] as $sort => $name) {
            $ids[] = $album->files()->create(['field' => 'photos', 'disk' => 'public', 'path' => "{$name}.jpg", 'filename' => "{$name}.jpg", 'sort' => $sort + 1])->id;
        }

        $this->deadlock('update', 'files', times: 2);
        $album->reorderFiles('photos', [$ids[1], $ids[0]]);
        $this->assertSame(['b.jpg' => 1, 'a.jpg' => 2], $album->files()->orderBy('sort')->pluck('sort', 'path')->all());

        $this->deadlock('update', 'files', times: 3);

        try {
            $album->reorderFiles('photos', [$ids[0], $ids[1]]);
            $this->fail('The order was written.');
        } catch (PDOException $e) {
            $this->assertStringStartsWith('Deadlock found', $e->getMessage());
        }

        $this->assertSame(['b.jpg' => 1, 'a.jpg' => 2], $album->files()->orderBy('sort')->pluck('sort', 'path')->all());
    }

    public function test_a_delete_is_tried_again_after_two_deadlocks(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $post = Post::query()->create(['name' => 'Gone']);
        $this->deadlock('delete', 'posts', times: 2);

        $this->delete("/admin/posts/{$post->id}")->assertRedirect();

        $this->assertSame(0, Post::query()->count());
        $this->assertSame(['atrium.record.deleted'], $this->heard);
    }

    public function test_a_delete_is_tried_again_when_what_its_listeners_write_hits_a_deadlock(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $post = Post::query()->create(['name' => 'Gone']);
        // After the row is deleted: the retry must not take the rolled back
        // delete for one that happened.
        Event::listen('eloquent.deleted: '.Post::class, static function (Post $deleted): void {
            Post::query()->create(['name' => 'Deleted '.$deleted->name]);
        });
        $this->deadlock('insert', 'posts', times: 1);

        $this->delete("/admin/posts/{$post->id}")->assertRedirect();

        $this->assertSame(['Deleted Gone'], Post::query()->pluck('name')->all());
        $this->assertSame(['atrium.record.deleted'], $this->heard);
    }

    /**
     * The first $times writes run and then fail as a deadlock does.
     */
    private function deadlock(string $verb, string $table, int $times): void
    {
        $left = $times;

        DB::listen(static function (QueryExecuted $query) use ($verb, $table, &$left): void {
            if ($left > 0 && preg_match('/^'.$verb.'\s.*\b'.$table.'\b/i', str_replace(['"', '`'], '', $query->sql)) === 1) {
                $left--;

                throw new PDOException('Deadlock found when trying to get lock; try restarting transaction');
            }
        });
    }
}
