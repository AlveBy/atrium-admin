<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\Tag;

/**
 * A record saved from the panel drops what the cache kept of it, even when
 * the models say nothing: the pivot rows and the SEO row are written by the
 * panel itself.
 */
final class AdminSaveCacheTest extends CacheTestCase
{
    /**
     * @return array<string,mixed>
     */
    private function form(Book $book, Tag $tag): array
    {
        return [
            'name' => ['en' => 'Edited'],
            'active' => true,
            'price' => 10,
            'shelf_id' => $book->shelf_id,
            'tags' => [$tag->id],
            'seo_title' => 'Edited title',
        ];
    }

    private function listVersion(): string
    {
        $name = 'list:'.Book::class;

        return $this->app->make(RecordCache::class)->versions()->get([$name])[$name];
    }

    public function test_a_save_from_the_panel_drops_the_record_even_when_model_events_are_muted(): void
    {
        [$book] = $this->books(1);
        $tag = Tag::query()->create(['name' => 'green']);
        Book::cached($book->id);
        $this->resetRequest();
        $before = $this->listVersion();

        Book::withoutEvents(fn () => $this->actingAs($this->admin(), 'admin')
            ->put("/admin/books/{$book->id}", $this->form($book, $tag))
            ->assertRedirect()
            ->assertSessionHasNoErrors());
        $this->resetRequest();

        $this->assertNotSame($before, $this->listVersion());
        $card = Book::cached($book->id);
        $this->assertSame('Edited', $card->name);
        $this->assertSame(['green'], $card->relation('tags')->pluck('name')->all());
        $this->assertSame('Edited title', $card->seo('title'));
    }

    public function test_a_save_that_failed_validation_drops_nothing(): void
    {
        [$book] = $this->books(1);
        $tag = Tag::query()->create(['name' => 'green']);
        Book::cached($book->id);
        $this->resetRequest();
        $before = $this->listVersion();

        Book::withoutEvents(fn () => $this->actingAs($this->admin(), 'admin')
            ->from('/admin/books')
            ->put("/admin/books/{$book->id}", [...$this->form($book, $tag), 'name' => ['en' => '']])
            ->assertSessionHasErrors());
        $this->resetRequest();

        $this->assertSame($before, $this->listVersion());
        $this->assertSame('Book 1', Book::cached($book->id)->name);
    }
}
