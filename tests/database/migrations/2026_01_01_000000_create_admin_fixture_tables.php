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
            $table->boolean('active')->default(true);
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

        Schema::create('remarks', function (Blueprint $table): void {
            $table->id();
            $table->string('text')->nullable();
            $table->nullableMorphs('subject');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['remarks', 'gadget_label', 'gadgets', 'pages', 'notes', 'stories', 'posts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
