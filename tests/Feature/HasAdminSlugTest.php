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
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;

final class HasAdminSlugTest extends TestCase
{
    /**
     * @return array<string,array{string,string}>
     */
    public static function names(): array
    {
        return [
            'Latin' => ['Garden chair', 'garden-chair'],
            'Cyrillic' => ['Садовый стул', 'sadovyy-stul'],
            'Cyrillic capitals' => ['ЁЛКА', 'yolka'],
            'hard and soft signs drop out' => ['Объём', 'obyom'],
            'digits and punctuation' => ['Tour #7, 2026!', 'tour-7-2026'],
            'HTML tags lose their brackets' => ['<b>Bold</b>', 'bboldb'],
            'only spaces' => ['   ', ''],
        ];
    }

    #[DataProvider('names')]
    public function test_a_new_record_without_a_code_gets_one_from_its_name(string $name, string $code): void
    {
        $article = Handbook::query()->create(['name' => $name]);

        $this->assertSame($code, $article->code);
        $this->assertSame($code, $article->fresh()?->code);
    }

    public function test_a_code_given_is_kept(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair', 'code' => 'my-chair']);

        $this->assertSame('my-chair', $article->code);
    }

    public function test_without_a_source_field_no_code_is_made(): void
    {
        $article = new class extends Handbook
        {
            public function getSlugSourceField(): string
            {
                return '';
            }
        };
        $article->fill(['name' => 'Garden chair'])->save();

        $this->assertNull($article->code);
        $this->assertSame(0, Slug::query()->count());
    }

    public function test_the_source_field_is_the_name(): void
    {
        $this->assertSame('name', (new Handbook)->getSlugSourceField());
    }

    public function test_every_cyrillic_letter_is_transliterated(): void
    {
        $article = new Handbook;

        $this->assertSame(
            'abvgdeyozhziyklmnoprstufhtschshschyeyuya',
            $article->transliterated('абвгдеёжзийклмнопрстуфхцчшщъыьэюя'),
        );
        $this->assertSame(
            'ABVGDEYoZhZIYKLMNOPRSTUFHTsChShSchYEYuYa',
            $article->transliterated('АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ'),
        );
        $this->assertSame('Hello, 世界', $article->transliterated('Hello, 世界'));
    }

    public function test_a_taken_code_gets_a_counter(): void
    {
        $first = Handbook::query()->create(['name' => 'Garden chair']);
        $second = Handbook::query()->create(['name' => 'Garden chair']);
        $third = Handbook::query()->create(['name' => 'Garden chair']);

        $this->assertSame('garden-chair', $first->code);
        $this->assertSame('garden-chair-1', $second->code);
        $this->assertSame('garden-chair-2', $third->code);
    }

    public function test_a_record_keeps_its_own_code_when_made_again(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair']);

        // The record itself does not count as taking the code.
        $this->assertSame('garden-chair', $article->generateSlug());
        $this->assertSame('garden-chair-1', (new Handbook(['name' => 'Garden chair']))->generateSlug());
    }

    public function test_a_new_record_gets_a_slug_row_and_a_cached_lookup(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair']);

        $slug = Slug::query()->sole();
        $this->assertSame('garden-chair', $slug->slug);
        $this->assertSame('garden-chair', $slug->full_path);
        $this->assertSame(Handbook::class, $slug->sluggable_type);
        $this->assertSame($article->id, (int) $slug->sluggable_id);
        $this->assertSame(['model' => Handbook::class, 'id' => $article->id], Cache::get('slug:garden-chair'));
    }

    public function test_the_cache_prefix_and_lifetime_come_from_the_config(): void
    {
        config()->set('atrium.slug.cache_prefix', 'paths');
        config()->set('atrium.slug.cache_ttl', 60);

        $article = Handbook::query()->create(['name' => 'Garden chair']);

        $this->assertSame(['model' => Handbook::class, 'id' => $article->id], Cache::get('paths:garden-chair'));
        $this->assertNull(Cache::get('slug:garden-chair'));

        $this->travel(61)->seconds();
        $this->assertNull(Cache::get('paths:garden-chair'));
    }

    public function test_without_a_configured_lifetime_a_slug_is_cached_for_a_day(): void
    {
        config()->set('atrium.slug', ['cache_prefix' => 'slug']);
        $this->freezeTime();

        // Written when the record is saved...
        Handbook::query()->create(['name' => 'Chair']);
        $this->travel(86399)->seconds();
        $this->assertNotNull(Cache::get('slug:chair'));
        $this->travel(1)->seconds();
        $this->assertNull(Cache::get('slug:chair'));

        // ...and when it is looked up.
        Handbook::findBySlug('chair');
        $this->travel(86399)->seconds();
        $this->assertNotNull(Cache::get('slug:chair'));
        $this->travel(1)->seconds();
        $this->assertNull(Cache::get('slug:chair'));
    }

    public function test_a_record_without_a_code_gets_no_slug_row(): void
    {
        Handbook::query()->create(['name' => '']);

        $this->assertSame(0, Slug::query()->count());
        $this->assertNull(Cache::get('slug:'));
    }

    public function test_a_child_path_starts_with_its_parent(): void
    {
        $parent = Handbook::query()->create(['name' => 'Tours']);
        $child = Handbook::query()->create(['name' => 'Italy', 'parent_id' => $parent->id]);

        $this->assertSame('tours/italy', $child->getFullPath());
        $this->assertSame('tours', $parent->getFullPath());
        $this->assertSame('tours/italy', Slug::query()->where('sluggable_id', $child->id)->value('full_path'));

        // No code of its own yet: just the parent's path, with no slash after it.
        $this->assertSame('tours', (new Handbook(['parent_id' => $parent->id]))->getFullPath());
        $this->assertSame('', (new Handbook)->getFullPath());
    }

    public function test_the_slug_of_a_record_without_a_code_is_empty(): void
    {
        $this->assertSame('', (new Handbook)->getSlug());
        $this->assertSame('', (new Handbook)->getFullPath());
        $this->assertSame('chair', (new Handbook(['code' => 'chair']))->getSlug());
    }

    public function test_changing_the_code_keeps_the_old_one_for_redirects(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair']);

        $article->update(['code' => 'chair']);

        $history = SlugHistory::query()->sole();
        $this->assertSame('garden-chair', $history->slug);
        $this->assertSame(Handbook::class, $history->sluggable_type);
        $this->assertSame($article->id, (int) $history->sluggable_id);
        // The path written is the one the record has after the change.
        $this->assertSame('chair', $history->full_path);

        $slug = Slug::query()->sole();
        $this->assertSame('chair', $slug->slug);
        $this->assertSame('chair', $slug->full_path);
        $this->assertSame(['model' => Handbook::class, 'id' => $article->id], Cache::get('slug:chair'));

        $this->assertSame('chair', Handbook::findRedirect('garden-chair'));
    }

    public function test_saving_without_changing_the_code_writes_no_history(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair']);

        $article->update(['name' => 'Kitchen chair']);

        $this->assertSame(0, SlugHistory::query()->count());
        $this->assertSame('garden-chair', Slug::query()->sole()->slug);
    }

    public function test_giving_a_code_to_a_record_without_one_writes_no_history(): void
    {
        $article = Handbook::query()->create(['name' => '']);

        $article->update(['code' => 'chair']);

        $this->assertSame(0, SlugHistory::query()->count());
        $this->assertSame('chair', Slug::query()->sole()->slug);
    }

    public function test_deleting_a_record_removes_its_slug_history_and_cache(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair']);
        $article->update(['code' => 'chair']);
        $other = Handbook::query()->create(['name' => 'Table']);
        $other->update(['code' => 'desk']);

        $article->delete();

        $this->assertSame(['table'], SlugHistory::query()->pluck('slug')->all());
        $this->assertSame(['desk'], Slug::query()->pluck('slug')->all());
        $this->assertNull(Cache::get('slug:chair'));
        $this->assertNotNull(Cache::get('slug:desk'));
    }

    public function test_update_slug_moves_the_record_and_remembers_the_old_code(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair']);
        Handbook::query()->create(['name' => 'Chair', 'code' => 'chair']);

        $article->updateSlug('chair');

        $this->assertSame('chair-1', $article->code);
        $this->assertSame('chair-1', $article->fresh()?->code);
        $this->assertSame(['garden-chair'], SlugHistory::query()->pluck('slug')->all());
        $this->assertSame('chair-1', Slug::query()->where('sluggable_id', $article->id)->value('slug'));
        $this->assertSame(['model' => Handbook::class, 'id' => $article->id], Cache::get('slug:chair-1'));
    }

    public function test_update_slug_to_the_same_code_changes_nothing(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair']);
        $updatedAt = $article->updated_at;
        $this->travel(5)->minutes();

        $article->updateSlug('garden-chair');

        $this->assertSame(0, SlugHistory::query()->count());
        $this->assertEquals($updatedAt, $article->fresh()?->updated_at);
    }

    public function test_update_slug_of_a_record_without_a_code_writes_no_history(): void
    {
        $article = Handbook::query()->create(['name' => '']);

        $article->updateSlug('chair');

        $this->assertSame('chair', $article->fresh()?->code);
        $this->assertSame(0, SlugHistory::query()->count());
        $this->assertSame('chair', Slug::query()->sole()->slug);
    }

    public function test_find_by_slug_reads_the_database_and_caches_the_answer(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair', 'code' => 'chair']);
        Cache::flush();

        $found = Handbook::findBySlug('chair');

        $this->assertTrue($found?->is($article));
        $this->assertSame(['model' => Handbook::class, 'id' => $article->id], Cache::get('slug:chair'));
    }

    public function test_find_by_slug_trusts_the_cache_of_its_own_model(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair', 'code' => 'chair']);
        $other = Handbook::query()->create(['name' => 'Table', 'code' => 'table']);
        Cache::put('slug:chair', ['model' => Handbook::class, 'id' => $other->id]);

        $this->assertTrue(Handbook::findBySlug('chair')?->is($other));
        $this->assertFalse(Handbook::findBySlug('chair')?->is($article));
    }

    public function test_find_by_slug_ignores_the_cache_of_another_model(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair', 'code' => 'chair']);
        $post = Post::query()->create(['name' => 'Hello']);
        Cache::put('slug:chair', ['model' => Post::class, 'id' => $post->id]);

        $this->assertTrue(Handbook::findBySlug('chair')?->is($article));
        $this->assertSame(['model' => Handbook::class, 'id' => $article->id], Cache::get('slug:chair'));
    }

    public function test_find_by_slug_of_an_unknown_code_is_null_and_caches_nothing(): void
    {
        Handbook::query()->create(['name' => 'Garden chair']);

        $this->assertNull(Handbook::findBySlug('missing'));
        $this->assertNull(Cache::get('slug:missing'));
    }

    public function test_find_by_full_path_finds_the_record_of_its_own_model_only(): void
    {
        $parent = Handbook::query()->create(['name' => 'Tours']);
        $child = Handbook::query()->create(['name' => 'Italy', 'parent_id' => $parent->id]);
        Slug::query()->create(['slug' => 'italy', 'full_path' => 'tours/italy', 'sluggable_type' => Post::class, 'sluggable_id' => 1]);

        $this->assertTrue(Handbook::findByFullPath('tours/italy')?->is($child));
        $this->assertNull(Handbook::findByFullPath('italy'));
        $this->assertNull(Handbook::findByFullPath('missing'));
    }

    public function test_find_redirect_of_an_unknown_code_is_null(): void
    {
        $article = Handbook::query()->create(['name' => 'Garden chair']);
        $article->update(['code' => 'chair']);
        SlugHistory::query()->create(['slug' => 'old-post', 'full_path' => 'old-post', 'sluggable_type' => Post::class, 'sluggable_id' => 1]);

        $this->assertNull(Handbook::findRedirect('missing'));
        $this->assertNull(Handbook::findRedirect('old-post'));
        $this->assertSame('chair', Handbook::findRedirect('garden-chair'));
    }

    public function test_find_redirect_of_a_deleted_record_is_null(): void
    {
        SlugHistory::query()->create(['slug' => 'gone', 'full_path' => 'gone', 'sluggable_type' => Handbook::class, 'sluggable_id' => 99]);

        $this->assertNull(Handbook::findRedirect('gone'));
    }
}
