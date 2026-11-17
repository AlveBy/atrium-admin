<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\Slug;
use Atrium\Admin\Models\SlugHistory;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

final class SlugModelsTest extends TestCase
{
    public function test_a_slug_is_found_by_its_code_and_type(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);
        $post = Post::query()->create(['name' => 'Hello']);
        Slug::query()->create(['slug' => 'chair', 'full_path' => 'blog/chair', 'sluggable_type' => Post::class, 'sluggable_id' => $post->id]);

        $this->assertSame(Handbook::class, Slug::findBySlug('chair', Handbook::class)?->sluggable_type);
        $this->assertSame(Post::class, Slug::findBySlug('chair', Post::class)?->sluggable_type);
        $this->assertNotNull(Slug::findBySlug('chair'));
        $this->assertNull(Slug::findBySlug('table'));
        $this->assertNull(Slug::findBySlug('chair', Model::class));

        $this->assertTrue(Slug::resolve('chair', Handbook::class)?->is($article));
        $this->assertTrue(Slug::resolve('chair', Post::class)?->is($post));
        $this->assertNull(Slug::resolve('table'));
    }

    public function test_a_slug_is_found_by_its_path_and_type(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);
        $post = Post::query()->create(['name' => 'Hello']);
        Slug::query()->create(['slug' => 'chair', 'full_path' => 'blog/chair', 'sluggable_type' => Post::class, 'sluggable_id' => $post->id]);

        $this->assertSame(Post::class, Slug::findByPath('blog/chair')?->sluggable_type);
        $this->assertSame(Handbook::class, Slug::findByPath('chair', Handbook::class)?->sluggable_type);
        $this->assertNull(Slug::findByPath('blog/chair', Handbook::class));
        $this->assertNull(Slug::findByPath('missing'));

        $this->assertTrue(Slug::resolvePath('chair', Handbook::class)?->is($article));
        $this->assertTrue(Slug::resolvePath('blog/chair')?->is($post));
        $this->assertNull(Slug::resolvePath('missing'));
    }

    public function test_a_redirect_leads_to_the_current_path_of_the_record(): void
    {
        $parent = Handbook::query()->create(['name' => 'Tours']);
        $child = Handbook::query()->create(['name' => 'Italy', 'parent_id' => $parent->id]);
        SlugHistory::query()->create(['slug' => 'old-italy', 'full_path' => 'trips/old-italy', 'sluggable_type' => Handbook::class, 'sluggable_id' => $child->id]);

        $this->assertSame('tours/italy', SlugHistory::findRedirect('old-italy'));
        $this->assertSame('tours/italy', SlugHistory::findRedirect('old-italy', Handbook::class));
        $this->assertNull(SlugHistory::findRedirect('old-italy', Post::class));
        $this->assertNull(SlugHistory::findRedirect('missing'));

        $this->assertSame('tours/italy', SlugHistory::findRedirectPath('trips/old-italy'));
        $this->assertSame('tours/italy', SlugHistory::findRedirectPath('trips/old-italy', Handbook::class));
        $this->assertNull(SlugHistory::findRedirectPath('trips/old-italy', Post::class));
        $this->assertNull(SlugHistory::findRedirectPath('missing'));
    }

    public function test_a_redirect_to_a_record_without_paths_leads_to_its_code(): void
    {
        $record = CodedRecord::query()->create(['name' => 'Chair', 'code' => 'chair']);
        $nameless = CodedRecord::query()->create(['name' => 'Table']);
        SlugHistory::query()->create(['slug' => 'old-chair', 'full_path' => 'shop/old-chair', 'sluggable_type' => CodedRecord::class, 'sluggable_id' => $record->id]);
        SlugHistory::query()->create(['slug' => 'old-table', 'full_path' => 'shop/old-table', 'sluggable_type' => CodedRecord::class, 'sluggable_id' => $nameless->id]);

        $this->assertSame('chair', SlugHistory::findRedirect('old-chair'));
        $this->assertSame('chair', SlugHistory::findRedirectPath('shop/old-chair'));
        $this->assertNull(SlugHistory::findRedirect('old-table'));
        $this->assertNull(SlugHistory::findRedirectPath('shop/old-table'));
    }

    public function test_a_redirect_to_a_deleted_record_is_null(): void
    {
        SlugHistory::query()->create(['slug' => 'gone', 'full_path' => 'shop/gone', 'sluggable_type' => Handbook::class, 'sluggable_id' => 99]);

        $this->assertNull(SlugHistory::findRedirect('gone'));
        $this->assertNull(SlugHistory::findRedirectPath('shop/gone'));
    }

    public function test_cleanup_keeps_history_younger_than_a_year_by_default(): void
    {
        $this->travelTo(now()->subDays(365)->subMinute());
        SlugHistory::query()->create(['slug' => 'old', 'full_path' => 'old', 'sluggable_type' => Handbook::class, 'sluggable_id' => 1]);
        $this->travelBack();
        $this->travelTo(now()->subDays(365)->addMinute());
        SlugHistory::query()->create(['slug' => 'young', 'full_path' => 'young', 'sluggable_type' => Handbook::class, 'sluggable_id' => 1]);
        $this->travelBack();

        $this->assertSame(1, SlugHistory::cleanup());
        $this->assertSame(['young'], SlugHistory::query()->pluck('slug')->all());
    }
}

final class CodedRecord extends Model
{
    protected $table = 'articles';

    protected $guarded = [];
}
