<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables of the record cache fixtures: created once per database, so the
 * tests run on MySQL and PostgreSQL as they do on SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
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

        foreach (['cache_screen_zebras', 'cache_screen_aardvarks'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
            });
        }

        Schema::create('card_owners', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
        });

        Schema::create('card_labels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('card_sections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code')->nullable();
        });

        Schema::create('card_items', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
            $table->json('summary')->nullable();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('link_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('picture')->nullable();
            $table->text('files')->nullable();
            $table->text('docs')->nullable();
        });

        Schema::create('card_item_label', function (Blueprint $table): void {
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('label_id');
        });

        Schema::create('card_item_related', function (Blueprint $table): void {
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('related_id');
        });
    }
};
