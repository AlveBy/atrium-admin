<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media library — a shared store of images and files for all admin fields.
 *
 * Before it, every image field could only "upload from computer", and the same
 * photo was uploaded again on every page that needed it. Bitrix had a media
 * library for this (the fileman module, b_medialib_* tables), and content
 * managers did use it.
 *
 * `url` is what goes into a model field: a root-relative path like
 * `/upload/medialibrary/fca/....jpg`. It is also the key by which a file is
 * recognised again. `disk`/`path` are filled only for files managed by
 * Storage; files migrated from Bitrix live right in public/ and leave these
 * columns empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bitrix_id')->nullable()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('media_library_collections')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('keywords')->nullable();
            $table->integer('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('media_library_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bitrix_id')->nullable()->unique();
            $table->foreignId('collection_id')->nullable()->constrained('media_library_collections')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('keywords')->nullable();
            $table->string('url', 500)->unique();
            $table->string('disk', 32)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('extension', 16)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('is_image')->default(true);
            $table->timestamps();

            // The list is browsed by date and filtered by type and collection.
            $table->index(['is_image', 'created_at']);
            $table->index('collection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_items');
        Schema::dropIfExists('media_library_collections');
    }
};
