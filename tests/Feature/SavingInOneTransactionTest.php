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
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Tests\Fixtures\Album;
use Atrium\Admin\Tests\Fixtures\Article;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;
use RuntimeException;

/**
 * A record is saved or deleted whole, with its relations and what its
 * listeners write, or not at all; the hooks are heard after the commit.
 */
final class SavingInOneTransactionTest extends TestCase
{
    /** @var array<int,string> */
    private array $heard = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Article::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['atrium.record.saved', 'atrium.record.deleted'] as $hook) {
            Hook::on($hook, function () use ($hook): void {
                $this->heard[] = $hook.' at level '.DB::transactionLevel();
            });
        }
    }

    public function test_an_administrator_is_not_left_saved_without_the_roles_that_failed(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $role = AdminRole::query()->create(['name' => 'Editors', 'permissions' => ['posts.view']]);
        $this->failOn('insert', 'admin_role_user');
        $this->withoutExceptionHandling();

        try {
            $this->post('/admin/users', [
                'name' => 'New', 'email' => 'new@example.com', 'is_active' => true,
                'password' => 'secret-password', 'password_confirmation' => 'secret-password',
                'roles' => [$role->id],
            ]);
            $this->fail('The roles were not written.');
        } catch (RuntimeException $e) {
            $this->assertSame('The write failed.', $e->getMessage());
        }

        $this->assertFalse(AdminUser::query()->where('email', 'new@example.com')->exists());
        $this->assertSame(0, DB::table('admin_role_user')->count());
        $this->assertSame([], $this->heard);
    }

    public function test_the_seo_tab_and_what_after_admin_save_writes_are_saved_with_the_record(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/articles', ['name' => 'Hello', 'seo_title' => 'Hello, world'])->assertRedirect();

        $article = Article::query()->where('name', 'Hello')->sole();
        $this->assertSame(['Hello', 'Index of Hello'], Post::query()->orderBy('id')->pluck('name')->all());
        $this->assertSame('Hello, world', SeoMeta::query()->sole()->title);
        $this->assertSame($article->id, SeoMeta::query()->sole()->seoable_id);
    }

    public function test_the_record_and_its_seo_are_not_left_saved_when_after_admin_save_fails(): void
    {
        $this->actingAs($this->admin(), 'admin');
        // The article is the first row written to posts, its index the second.
        $this->failOn('insert', 'posts', after: 1);
        $this->withoutExceptionHandling();

        try {
            $this->post('/admin/articles', ['name' => 'Hello', 'seo_title' => 'Hello, world']);
            $this->fail('The index was written.');
        } catch (RuntimeException $e) {
            $this->assertSame('The write failed.', $e->getMessage());
        }

        $this->assertSame(0, Post::query()->count());
        $this->assertSame(0, SeoMeta::query()->count());
        $this->assertSame([], $this->heard);
    }

    public function test_a_record_its_before_admin_delete_refuses_stays_in_the_panel_and_the_api(): void
    {
        $admin = $this->admin();
        $article = Article::query()->create(['name' => 'Pinned']);

        $this->actingAs($admin, 'admin')->from('/admin/articles')
            ->delete("/admin/articles/{$article->id}")
            ->assertRedirect('/admin/articles')
            ->assertSessionHas('error', __('atrium::resource.delete_failed'));
        $this->withToken($admin->createToken('agent', ['articles:write'])->plainTextToken)
            ->deleteJson("/api/v1/articles/{$article->id}")->assertStatus(409);

        $this->assertSame(['Pinned'], Post::query()->pluck('name')->all());
        $this->assertSame([], $this->heard);
    }

    public function test_the_saved_hook_is_heard_after_the_commit_of_the_outermost_transaction(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $level = DB::transactionLevel();

        $this->post('/admin/posts', ['name' => 'First'])->assertRedirect();
        $this->assertSame(["atrium.record.saved at level {$level}"], $this->heard);

        DB::transaction(function (): void {
            $this->post('/admin/posts', ['name' => 'Second'])->assertRedirect();
            $this->assertCount(1, $this->heard, 'Not before the caller commits.');
        });

        $this->assertSame(["atrium.record.saved at level {$level}", "atrium.record.saved at level {$level}"], $this->heard);
        $this->assertSame(['First', 'Second'], Post::query()->orderBy('id')->pluck('name')->all());
    }

    public function test_what_the_delete_wrote_is_taken_back_when_the_delete_fails(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $post = Post::query()->create(['name' => 'Keep me']);
        Event::listen('eloquent.deleting: '.Post::class, static function (Post $deleting): void {
            if ($deleting->name === 'Keep me') {
                Post::query()->create(['name' => 'Written while deleting']);
            }
        });
        $this->failOn('delete', 'posts');
        $this->withoutExceptionHandling();

        try {
            $this->delete("/admin/posts/{$post->id}");
            $this->fail('The delete did not fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('The write failed.', $e->getMessage());
        }

        $this->assertSame(['Keep me'], Post::query()->pluck('name')->all());
        $this->assertSame([], $this->heard);
    }

    public function test_a_deleted_record_is_heard_after_the_commit_from_the_panel_and_the_api(): void
    {
        $admin = $this->admin();
        $level = DB::transactionLevel();
        [$first, $second] = [Post::query()->create(['name' => 'First']), Post::query()->create(['name' => 'Second'])];

        $this->actingAs($admin, 'admin')->delete("/admin/posts/{$first->id}")->assertRedirect();
        $this->withToken($admin->createToken('agent', ['posts:write'])->plainTextToken)
            ->deleteJson("/api/v1/posts/{$second->id}")->assertNoContent();

        $this->assertSame(["atrium.record.deleted at level {$level}", "atrium.record.deleted at level {$level}"], $this->heard);

        $third = Post::query()->create(['name' => 'Third']);
        DB::transaction(function () use ($third): void {
            $this->delete("/admin/posts/{$third->id}")->assertRedirect();
            $this->assertCount(2, $this->heard, 'Not before the caller commits.');
        });

        $this->assertCount(3, $this->heard);
        $this->assertSame(0, Post::query()->count());
    }

    public function test_an_administrator_is_not_left_saved_without_the_superadmin_box(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->failOn('update', 'admin_users');
        $this->withoutExceptionHandling();

        try {
            $this->post('/admin/users', [
                'name' => 'New', 'email' => 'new@example.com', 'is_active' => true, 'is_superadmin' => true,
                'password' => 'secret-password', 'password_confirmation' => 'secret-password',
            ]);
            $this->fail('The box was not written.');
        } catch (RuntimeException $e) {
            $this->assertSame('The write failed.', $e->getMessage());
        }

        $this->assertFalse(AdminUser::query()->where('email', 'new@example.com')->exists());
        $this->assertSame([], $this->heard);
    }

    public function test_files_keep_their_old_order_when_the_new_one_is_not_written_whole(): void
    {
        $album = Album::query()->create(['name' => 'Summer']);
        $ids = [];
        foreach (['a', 'b', 'c'] as $sort => $name) {
            $ids[] = $album->files()->create(['field' => 'photos', 'disk' => 'public', 'path' => "{$name}.jpg", 'filename' => "{$name}.jpg", 'sort' => $sort + 1])->id;
        }

        $album->reorderFiles('photos', [$ids[2], $ids[0], $ids[1]]);
        $this->assertSame(['c.jpg' => 1, 'a.jpg' => 2, 'b.jpg' => 3], $album->files()->orderBy('sort')->pluck('sort', 'path')->all());

        $this->failOn('update', 'files', after: 1);

        try {
            $album->reorderFiles('photos', [$ids[0], $ids[1], $ids[2]]);
            $this->fail('The order was written.');
        } catch (RuntimeException $e) {
            $this->assertSame('The write failed.', $e->getMessage());
        }

        $this->assertSame(['c.jpg' => 1, 'a.jpg' => 2, 'b.jpg' => 3], $album->files()->orderBy('sort')->pluck('sort', 'path')->all());
    }

    /**
     * Make a write fail after the database has run it, as a constraint or a
     * lost connection would; the first $after such writes go through.
     */
    private function failOn(string $verb, string $table, int $after = 0): void
    {
        DB::listen(static function (QueryExecuted $query) use ($verb, $table, &$after): void {
            if (preg_match('/^'.$verb.'\s.*\b'.$table.'\b/i', str_replace(['"', '`'], '', $query->sql)) === 1 && $after-- === 0) {
                throw new RuntimeException('The write failed.');
            }
        });
    }
}
