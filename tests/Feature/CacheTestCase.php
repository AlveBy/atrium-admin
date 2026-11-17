<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Cache\CacheStore;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Cache\Versions;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\Shelf;
use Atrium\Admin\Tests\Fixtures\Cache\SpyRepository;
use Atrium\Admin\Tests\Fixtures\Cache\Tag;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Books on shelves with tags, translated, with pictures and SEO: what the
 * record cache tests build their records from.
 */
abstract class CacheTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Book::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('cache_shelves', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
            $table->timestamps();
        });

        Schema::create('cache_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('cache_books', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('price')->nullable();
            $table->foreignId('shelf_id')->nullable();
            $table->string('cover')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });

        Schema::create('cache_codes', function (Blueprint $table): void {
            $table->string('code')->primary();
        });

        Schema::create('cache_nodes', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('cache_parts', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
        });

        Schema::create('cache_book_tag', function (Blueprint $table): void {
            $table->foreignId('book_id');
            $table->foreignId('tag_id');
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
    }

    /**
     * @return array<int,Book>
     */
    protected function books(int $count, string $prefix = 'Book'): array
    {
        $books = [];
        $red = Tag::query()->firstOrCreate(['name' => 'red']);
        $blue = Tag::query()->firstOrCreate(['name' => 'blue']);

        for ($i = 1; $i <= $count; $i++) {
            $shelf = Shelf::query()->create(['name' => ['en' => 'Shelf '.$i]]);
            $book = Book::query()->create([
                'name' => ['en' => $prefix.' '.$i],
                'price' => $i * 1000,
                'shelf_id' => $shelf->id,
                'cover' => 'books/cover-'.$i.'.jpg',
                'photos' => ['books/a.jpg', 'books/b.jpg'],
            ]);
            $book->tags()->attach([$red->id, $blue->id]);
            $books[] = $book;
        }

        return $books;
    }

    /**
     * What a new request starts with: nothing read yet.
     */
    protected function resetRequest(): void
    {
        $this->app->make(RecordCache::class)->reset();
    }

    /**
     * A record cache of its own on a store that writes down every call, to
     * read the keys and the times to live it uses. Without tags by default:
     * then a key is `atrium:` and what the cache made of it.
     *
     * @return array{0:RecordCache,1:SpyRepository}
     */
    protected function spyCache(bool $tags = false): array
    {
        $repository = new SpyRepository;

        return [new RecordCache(new CacheStore($repository, $tags), new Versions($repository)), $repository];
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return array{0:int,1:T}
     */
    protected function countQueries(callable $callback): array
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $before = $count;
        $result = $callback();
        $queries = $count - $before;
        // Further listeners keep counting; only this window matters.
        $count = PHP_INT_MIN;

        return [$queries, $result];
    }
}
