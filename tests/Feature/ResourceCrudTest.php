<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Inertia\Testing\AssertableInertia;

final class ResourceCrudTest extends TestCase
{
    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin/posts')->assertRedirect('/admin/login');
    }

    public function test_the_list_uses_the_generic_page_and_knows_its_routes(): void
    {
        Post::query()->create(['name' => 'Hello']);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/posts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Resource/Index')
                ->where('routeName', 'admin.posts')
                ->where('title', 'Posts')
                ->where('items.total', 1)
                ->where('items.data.0.name', 'Hello'));
    }

    public function test_search_filters_by_searchable_columns(): void
    {
        Post::query()->create(['name' => 'Apple']);
        Post::query()->create(['name' => 'Banana']);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/posts?search=nan')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.total', 1)
                ->where('items.data.0.name', 'Banana'));
    }

    public function test_create_store_edit_update_destroy(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/posts/create')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Resource/Create'));

        $this->post('/admin/posts', ['name' => 'First', 'active' => true, 'sort' => 10])
            ->assertRedirect(route('admin.posts.index'));

        $post = Post::query()->sole();
        $this->assertSame('First', $post->name);
        $this->assertSame(10, $post->sort);

        $this->get("/admin/posts/{$post->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Resource/Edit')
                ->where('item.name', 'First'));

        $this->put("/admin/posts/{$post->id}", ['name' => 'Renamed', 'active' => false, 'sort' => 10])
            ->assertRedirect(route('admin.posts.index'));

        $this->assertSame('Renamed', $post->fresh()->name);
        $this->assertFalse($post->fresh()->active);

        $this->delete("/admin/posts/{$post->id}")->assertRedirect(route('admin.posts.index'));
        $this->assertSame(0, Post::query()->count());
    }

    public function test_required_fields_are_validated_from_the_field_declarations(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/posts/create')
            ->post('/admin/posts', ['name' => ''])
            ->assertRedirect('/admin/posts/create')
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Post::query()->count());
    }

    public function test_an_untouched_checkbox_saves_as_false_instead_of_failing(): void
    {
        // The form sends '' for a checkbox nobody touched; it must not reach a
        // NOT NULL boolean column as null.
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/posts', ['name' => 'Box', 'active' => '', 'sort' => 1])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Post::query()->sole()->active);
    }

    public function test_a_missing_record_is_a_404(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin/posts/999/edit')->assertNotFound();
    }
}
