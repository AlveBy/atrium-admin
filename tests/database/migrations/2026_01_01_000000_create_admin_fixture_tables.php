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
 * Tables of the fixture models in tests/Fixtures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('parcels', function (Blueprint $table): void {
            $table->id();
            $table->string('article')->nullable();
            $table->json('name');
            $table->string('code')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->string('booking')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->date('starts_on')->nullable();
            $table->string('label')->nullable();
            $table->json('colors')->nullable();
            $table->json('features')->nullable();
            $table->string('picture')->nullable();
            $table->json('photos')->nullable();
            $table->unsignedBigInteger('aisle_id')->nullable();
            $table->unsignedBigInteger('post_id')->nullable();
            $table->text('detail_text')->nullable();
            $table->integer('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->index();
            $table->json('name');
            $table->json('code');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->index();
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('code')->nullable();
            $table->integer('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('gadgets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('label_id')->nullable();
            $table->timestamps();
        });

        Schema::create('gadget_label', function (Blueprint $table): void {
            $table->foreignId('gadget_id');
            $table->foreignId('label_id');
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->unsignedBigInteger('section_parent')->nullable();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('code')->nullable();
            $table->text('preview_text')->nullable();
            $table->text('description')->nullable();
            $table->string('preview_picture')->nullable();
            $table->string('detail_picture')->nullable();
            $table->timestamps();
        });

        Schema::create('remarks', function (Blueprint $table): void {
            $table->id();
            $table->string('text')->nullable();
            $table->nullableMorphs('subject');
            $table->timestamps();
        });
        Schema::create('api_aisles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable();
            $table->integer('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('chapters', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->string('kind')->nullable();
            $table->timestamps();
        });

        Schema::create('guides', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('folder')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('topics', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('slug')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->timestamps();
        });

        Schema::create('bundles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('bundle_post', function (Blueprint $table): void {
            $table->foreignId('bundle_id');
            $table->foreignId('post_id');
        });

        Schema::create('bundle_topic', function (Blueprint $table): void {
            $table->foreignId('bundle_id');
            $table->foreignId('topic_id');
        });

        Schema::create('kits', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('post_id')->nullable();
            $table->timestamps();
        });

        foreach (['post', 'note', 'page', 'topic'] as $related) {
            Schema::create("kit_{$related}", function (Blueprint $table) use ($related): void {
                $table->foreignId('kit_id');
                $table->foreignId("{$related}_id");
                $table->integer('sort')->default(0);
            });
        }

        Schema::create('leaflets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('code')->nullable();
            $table->integer('sort')->default(500);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['kit_post', 'kit_note', 'kit_page', 'kit_topic', 'kits', 'sections', 'bundle_topic', 'bundle_post', 'bundles', 'leaflets', 'topics', 'guides', 'chapters', 'api_aisles', 'remarks', 'articles', 'gadget_label', 'gadgets', 'pages', 'notes', 'stories', 'parcels', 'posts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
