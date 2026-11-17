<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\MediaCollection;
use Atrium\Admin\Models\MediaItem;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

final class MediaAndSeoModelsTest extends TestCase
{
    use PinsExtensionSurface;

    public function test_the_effective_title_and_description_fall_back_in_order(): void
    {
        $meta = new SeoMeta(['title' => 'Title', 'og_title' => 'Share title', 'description' => 'Text', 'og_description' => 'Share text']);
        $this->assertSame('Title', $meta->getEffectiveTitle('Default'));
        $this->assertSame('Text', $meta->getEffectiveDescription('Default'));

        $shareOnly = new SeoMeta(['og_title' => 'Share title', 'og_description' => 'Share text']);
        $this->assertSame('Share title', $shareOnly->getEffectiveTitle('Default'));
        $this->assertSame('Share text', $shareOnly->getEffectiveDescription('Default'));

        $empty = new SeoMeta(['title' => '', 'description' => '']);
        $this->assertSame('Default', $empty->getEffectiveTitle('Default'));
        $this->assertSame('Default', $empty->getEffectiveDescription('Default'));
        $this->assertSame('', $empty->getEffectiveTitle());
        $this->assertSame('', $empty->getEffectiveDescription());
    }

    public function test_the_share_title_and_text_default_to_the_page_ones(): void
    {
        $meta = new SeoMeta(['title' => 'Title', 'description' => 'Text']);
        $this->assertSame('Title', $meta->og_title);
        $this->assertSame('Text', $meta->og_description);

        $meta->fill(['og_title' => 'Share title', 'og_description' => 'Share text']);
        $this->assertSame('Share title', $meta->og_title);
        $this->assertSame('Share text', $meta->og_description);

        $this->assertNull((new SeoMeta)->og_title);
    }

    public function test_robots_default_to_index_and_can_be_switched(): void
    {
        $meta = new SeoMeta;
        $this->assertSame('index, follow', $meta->getRobotsContent());

        $this->assertSame($meta, $meta->noindex());
        $this->assertSame('noindex, nofollow', $meta->getRobotsContent());

        $this->assertSame($meta, $meta->index());
        $this->assertSame('index, follow', $meta->robots);
    }

    /**
     * @return array<string,array{?string,?string}>
     */
    public static function shareImages(): array
    {
        return [
            'none' => [null, null],
            'empty' => ['', null],
            'stored upload' => ['seo/share.jpg', 'http://localhost/storage/seo/share.jpg'],
            'full address' => ['https://cdn.example.com/share.jpg', 'https://cdn.example.com/share.jpg'],
            'plain http' => ['http://cdn.example.com/share.jpg', 'http://cdn.example.com/share.jpg'],
        ];
    }

    #[DataProvider('shareImages')]
    public function test_the_share_image_address(?string $image, ?string $url): void
    {
        $this->assertSame($url, (new SeoMeta(['og_image' => $image]))->og_image_url);
    }

    public function test_seo_meta_belongs_to_its_record(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair']);
        $meta = $article->updateSeoMeta(['title' => 'Chair']);

        $this->assertTrue($meta->seoable?->is($article));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function codes(): array
    {
        return [
            'plain' => ['en', 'en'],
            'with a region' => ['pt-br', 'pt-BR'],
            'with a script' => ['zh-hans', 'zh-HANS'],
            'three letters' => ['kaa', 'kaa'],
        ];
    }

    #[DataProvider('codes')]
    public function test_a_language_code_becomes_an_hreflang(string $code, string $hreflang): void
    {
        $this->assertSame($hreflang, (new Language(['code' => $code]))->hreflang());
    }

    private function item(string $name, array $attributes = []): MediaItem
    {
        return MediaItem::query()->create(['name' => $name, 'url' => '/upload/'.md5($name).'.jpg', ...$attributes]);
    }

    public function test_media_items_are_split_into_images_and_documents(): void
    {
        $photo = $this->item('Photo');
        $manual = $this->item('Manual', ['is_image' => false]);

        $this->assertSame([$photo->id], MediaItem::query()->images()->pluck('id')->all());
        $this->assertSame([$manual->id], MediaItem::query()->documents()->pluck('id')->all());
    }

    public function test_media_search_looks_in_the_name_keywords_and_description(): void
    {
        // Lower case: LIKE is case-sensitive on PostgreSQL (reported).
        $byName = $this->item('Big garden chair');
        $byKeywords = $this->item('IMG_1', ['keywords' => 'garden, summer']);
        $byDescription = $this->item('IMG_2', ['description' => 'A garden at dawn']);
        $this->item('Kitchen');

        $found = MediaItem::query()->search('  garden ')->orderBy('id')->pluck('id')->all();

        $this->assertSame([$byName->id, $byKeywords->id, $byDescription->id], $found);
        $this->assertCount(4, MediaItem::query()->search('')->get());
        $this->assertCount(4, MediaItem::query()->search('   ')->get());
        $this->assertSame([], MediaItem::query()->search('nothing like it')->pluck('id')->all());
    }

    public function test_media_search_escapes_wildcards_and_an_empty_term_adds_no_condition(): void
    {
        $this->assertSame(array_fill(0, 3, '%50!%!_x%'), MediaItem::query()->search(' 50%_x ')->getBindings());

        $this->assertSame(MediaItem::query()->toSql(), MediaItem::query()->search(' ')->toSql());
        $this->assertSame([], MediaItem::query()->search('')->getBindings());
    }

    public function test_media_search_takes_wildcards_literally(): void
    {
        if ($this->app['db']->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite LIKE has no escape character: "\\%" is not a literal percent sign there (reported, src not changed).');
        }

        $sale = $this->item('50% off');
        $this->item('500 offers');
        $snake = $this->item('file_name');
        $this->item('filename');

        $this->assertSame([$sale->id], MediaItem::query()->search('50%')->pluck('id')->all());
        $this->assertSame([$snake->id], MediaItem::query()->search('file_')->pluck('id')->all());
    }

    public function test_media_search_finds_unicode(): void
    {
        $cyrillic = $this->item('Садовый стул');
        $japanese = $this->item('ガーデンチェア');

        $this->assertSame([$cyrillic->id], MediaItem::query()->search('стул')->pluck('id')->all());
        $this->assertSame([$japanese->id], MediaItem::query()->search('チェア')->pluck('id')->all());
    }

    public function test_a_media_item_deletes_its_file_from_its_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/a.jpg', 'a');
        Storage::disk('public')->put('media/b.jpg', 'b');

        $this->item('A', ['disk' => 'public', 'path' => 'media/a.jpg'])->deleteFile();
        // A picture moved from another site has no disk or path: nothing to delete.
        $this->item('B', ['disk' => null, 'path' => 'media/b.jpg'])->deleteFile();
        $this->item('C', ['disk' => 'public', 'path' => null])->deleteFile();

        Storage::disk('public')->assertMissing('media/a.jpg');
        Storage::disk('public')->assertExists('media/b.jpg');
    }

    public function test_collections_hold_items_and_sub_collections(): void
    {
        $root = MediaCollection::query()->create(['name' => 'Root']);
        $child = MediaCollection::query()->create(['name' => 'Child', 'parent_id' => $root->id]);
        $item = $this->item('Photo', ['collection_id' => $child->id]);

        $this->assertSame([$child->id], $root->children->modelKeys());
        $this->assertSame([], $root->items->modelKeys());
        $this->assertSame([$item->id], $child->items->modelKeys());
        $this->assertTrue($item->collection?->is($child));
        $this->assertSame($child->id, $item->fresh()?->collection_id);
    }
}
