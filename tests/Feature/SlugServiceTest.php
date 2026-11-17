<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\Slug;
use Atrium\Admin\Models\SlugHistory;
use Atrium\Admin\Services\SlugService;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;

final class SlugServiceTest extends TestCase
{
    private function service(): SlugService
    {
        return new SlugService;
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function texts(): array
    {
        return [
            'Latin' => ['Garden chair', 'garden-chair'],
            'Cyrillic' => ['Щука в реке', 'schuka-v-reke'],
            'Cyrillic capitals' => ['ЖАРА', 'zhara'],
            'empty' => ['', ''],
            'only punctuation' => ['?!', ''],
            'emoji' => ['Chair 🪑', 'chair'],
        ];
    }

    #[DataProvider('texts')]
    public function test_a_slug_is_made_from_a_text(string $text, string $slug): void
    {
        $this->assertSame($slug, $this->service()->generate($text));
    }

    public function test_every_cyrillic_letter_is_transliterated(): void
    {
        $this->assertSame(
            'abvgdeyozhziyklmnoprstufhtschshschyeyuya',
            $this->service()->transliterate('абвгдеёжзийклмнопрстуфхцчшщъыьэюя'),
        );
        $this->assertSame(
            'ABVGDEYoZhZIYKLMNOPRSTUFHTsChShSchYEYuYa',
            $this->service()->transliterate('АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ'),
        );
        $this->assertSame('東京 Tokyo', $this->service()->transliterate('東京 Tokyo'));
    }

    public function test_a_unique_slug_counts_up_past_taken_codes(): void
    {
        Handbook::query()->create(['name' => 'One', 'code' => 'chair']);
        $taken = Handbook::query()->create(['name' => 'Two', 'code' => 'chair-1']);

        $this->assertSame('chair-2', $this->service()->generateUnique('Chair', Handbook::class));
        $this->assertSame('table', $this->service()->generateUnique('Table', Handbook::class));
        // The record being saved does not block its own code.
        $this->assertSame('chair-1', $this->service()->ensureUnique('chair', Handbook::class, $taken->id, 1));
        $this->assertSame('chair-1', $this->service()->generateUnique('Chair', Handbook::class, $taken->id));
    }

    public function test_find_by_slug_reads_the_database_then_the_cache(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);
        Cache::flush();

        $this->assertTrue($this->service()->findBySlug('chair', Handbook::class)?->is($article));
        $this->assertSame(['model' => Handbook::class, 'id' => $article->id], Cache::get('slug:Handbook:chair'));

        // From now on the cache answers, even for a code that changed since.
        Handbook::query()->whereKey($article->id)->update(['code' => 'other']);
        $this->assertTrue($this->service()->findBySlug('chair', Handbook::class)?->is($article));
    }

    public function test_find_by_slug_of_an_unknown_code_is_null_and_caches_nothing(): void
    {
        $this->assertNull($this->service()->findBySlug('missing', Handbook::class));
        $this->assertNull(Cache::get('slug:Handbook:missing'));
    }

    public function test_the_cache_prefix_and_lifetime_come_from_the_config(): void
    {
        config()->set('atrium.slug.cache_prefix', 'paths');
        config()->set('atrium.slug.cache_ttl', 60);
        $article = Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);
        Cache::flush();

        $this->service()->findBySlug('chair', Handbook::class);
        $this->service()->findByPath('chair');

        $this->assertSame(['model' => Handbook::class, 'id' => $article->id], Cache::get('paths:Handbook:chair'));
        $this->assertSame(['model' => Handbook::class, 'id' => $article->id], Cache::get('paths:path:chair'));
        $this->travel(61)->seconds();
        $this->assertNull(Cache::get('paths:Handbook:chair'));
        $this->assertNull(Cache::get('paths:path:chair'));
    }

    public function test_without_a_configured_lifetime_lookups_are_cached_for_a_day(): void
    {
        config()->set('atrium.slug', ['cache_prefix' => 'slug']);
        Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);
        Cache::flush();
        $this->freezeTime();

        $this->service()->findBySlug('chair', Handbook::class);
        $this->service()->findByPath('chair');

        $this->travel(86399)->seconds();
        $this->assertNotNull(Cache::get('slug:Handbook:chair'));
        $this->assertNotNull(Cache::get('slug:path:chair'));
        $this->travel(1)->seconds();
        $this->assertNull(Cache::get('slug:Handbook:chair'));
        $this->assertNull(Cache::get('slug:path:chair'));
    }

    public function test_find_by_path_reads_the_slug_table_then_the_cache(): void
    {
        $parent = Handbook::query()->create(['name' => 'Tours']);
        $child = Handbook::query()->create(['name' => 'Italy', 'parent_id' => $parent->id]);
        Cache::flush();

        $this->assertTrue($this->service()->findByPath('tours/italy')?->is($child));
        $this->assertSame(['model' => Handbook::class, 'id' => $child->id], Cache::get('slug:path:tours/italy'));

        Slug::query()->delete();
        $this->assertTrue($this->service()->findByPath('tours/italy')?->is($child));
        $this->assertTrue($this->service()->findByPath('tours/italy', Handbook::class)?->is($child));
    }

    public function test_find_by_path_skips_a_cached_record_of_another_model(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);
        $post = Post::query()->create(['name' => 'Hello']);
        Cache::put('slug:path:chair', ['model' => Post::class, 'id' => $post->id]);

        $this->assertTrue($this->service()->findByPath('chair', Handbook::class)?->is($article));
        $this->assertTrue($this->service()->findByPath('chair')?->is($article));
    }

    public function test_find_by_path_of_another_model_or_an_unknown_path_is_null(): void
    {
        Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);
        Cache::flush();

        $this->assertNull($this->service()->findByPath('chair', Post::class));
        $this->assertNull($this->service()->findByPath('missing'));
        $this->assertNull(Cache::get('slug:path:missing'));
    }

    public function test_find_by_path_of_a_slug_whose_record_is_gone_is_null(): void
    {
        Slug::query()->create(['slug' => 'gone', 'full_path' => 'gone', 'sluggable_type' => Handbook::class, 'sluggable_id' => 99]);

        $this->assertNull($this->service()->findByPath('gone'));
        $this->assertNull(Cache::get('slug:path:gone'));
    }

    public function test_redirects_come_from_the_history(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);
        $this->service()->saveToHistory($article, 'old-chair', 'shop/old-chair');

        $this->assertSame('chair', $this->service()->findRedirect('old-chair'));
        $this->assertSame('chair', $this->service()->findRedirect('old-chair', Handbook::class));
        $this->assertNull($this->service()->findRedirect('old-chair', Post::class));
        $this->assertSame('chair', $this->service()->findRedirectPath('shop/old-chair'));
        $this->assertNull($this->service()->findRedirectPath('old-chair'));
    }

    public function test_history_without_an_old_path_keeps_the_old_slug_as_the_path(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);

        $this->service()->saveToHistory($article, 'old-chair');
        $this->service()->saveToHistory($article, 'old-chair');

        $history = SlugHistory::query()->sole();
        $this->assertSame('old-chair', $history->full_path);
        $this->assertSame(Handbook::class, $history->sluggable_type);
        $this->assertSame($article->id, (int) $history->sluggable_id);
    }

    public function test_sync_slug_writes_the_slug_row_and_both_caches(): void
    {
        $parent = Handbook::query()->create(['name' => 'Tours']);
        $child = Handbook::query()->create(['name' => 'Italy', 'parent_id' => $parent->id]);
        Slug::query()->delete();
        Cache::flush();

        $this->service()->syncSlug($child);
        $this->service()->syncSlug($child);

        $slug = Slug::query()->sole();
        $this->assertSame('italy', $slug->slug);
        $this->assertSame('tours/italy', $slug->full_path);
        $this->assertSame(Handbook::class, $slug->sluggable_type);
        $this->assertSame($child->id, (int) $slug->sluggable_id);
        $this->assertSame(['model' => Handbook::class, 'id' => $child->id], Cache::get('slug:Handbook:italy'));
        $this->assertSame(['model' => Handbook::class, 'id' => $child->id], Cache::get('slug:path:tours/italy'));
    }

    public function test_sync_slug_skips_models_without_slugs_or_codes(): void
    {
        $post = Post::query()->create(['name' => 'Hello']);
        $article = Handbook::query()->create(['name' => '']);
        Cache::flush();

        $this->service()->syncSlug($post);
        $this->service()->syncSlug($article);

        $this->assertSame(0, Slug::query()->count());
        $this->assertNull(Cache::get('slug:path:'));
    }

    public function test_clear_cache_forgets_both_keys_of_a_model(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);
        Cache::put('slug:Handbook:chair', ['model' => Handbook::class, 'id' => $article->id]);
        Cache::put('slug:path:chair', ['model' => Handbook::class, 'id' => $article->id]);
        Cache::put('slug:Handbook:table', ['model' => Handbook::class, 'id' => 2]);

        $this->service()->clearCache($article);

        $this->assertNull(Cache::get('slug:Handbook:chair'));
        $this->assertNull(Cache::get('slug:path:chair'));
        $this->assertNotNull(Cache::get('slug:Handbook:table'));
    }

    public function test_clear_cache_of_a_model_without_slugs_does_nothing(): void
    {
        $post = Post::query()->create(['name' => 'Hello']);
        Cache::put('slug:Post:', ['model' => Post::class, 'id' => $post->id]);

        $this->service()->clearCache($post);

        $this->assertNotNull(Cache::get('slug:Post:'));
    }

    public function test_regenerate_all_syncs_every_record_with_a_slug(): void
    {
        Handbook::query()->create(['name' => 'Chair']);
        Handbook::query()->create(['name' => 'Table']);
        Slug::query()->delete();

        $this->assertSame(2, $this->service()->regenerateAll(Handbook::class));
        $this->assertSame(['chair', 'table'], Slug::query()->orderBy('slug')->pluck('slug')->all());

        Post::query()->create(['name' => 'Hello']);
        $this->assertSame(0, $this->service()->regenerateAll(Post::class));
    }

    public function test_cleanup_drops_history_older_than_the_given_days(): void
    {
        $this->travelTo(now()->subDays(400));
        SlugHistory::query()->create(['slug' => 'ancient', 'full_path' => 'ancient', 'sluggable_type' => Handbook::class, 'sluggable_id' => 1]);
        $this->travelBack();
        $this->travelTo(now()->subDays(20));
        SlugHistory::query()->create(['slug' => 'recent', 'full_path' => 'recent', 'sluggable_type' => Handbook::class, 'sluggable_id' => 1]);
        $this->travelBack();

        $this->assertSame(1, $this->service()->cleanupHistory());
        $this->assertSame(['recent'], SlugHistory::query()->pluck('slug')->all());
        $this->assertSame(1, $this->service()->cleanupHistory(10));
        $this->assertSame(0, SlugHistory::query()->count());
    }

    public function test_cleanup_keeps_a_year_of_history_by_default(): void
    {
        $this->travelTo(now()->subDays(365)->subMinute());
        SlugHistory::query()->create(['slug' => 'old', 'full_path' => 'old', 'sluggable_type' => Handbook::class, 'sluggable_id' => 1]);
        $this->travelBack();
        $this->travelTo(now()->subDays(365)->addMinute());
        SlugHistory::query()->create(['slug' => 'young', 'full_path' => 'young', 'sluggable_type' => Handbook::class, 'sluggable_id' => 1]);
        $this->travelBack();

        $this->assertSame(1, $this->service()->cleanupHistory());
        $this->assertSame(['young'], SlugHistory::query()->pluck('slug')->all());
    }
}
