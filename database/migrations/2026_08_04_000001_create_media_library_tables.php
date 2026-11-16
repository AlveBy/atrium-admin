<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Медиабиблиотека — общий склад картинок и файлов для всех полей админки.
 *
 * До неё каждое поле с картинкой умело только «загрузить с компьютера», и
 * одна и та же фотография заливалась заново на каждой странице, где нужна.
 * В Битриксе для этого была медиабиблиотека (модуль fileman, таблицы
 * b_medialib_*), и контент-менеджеры ей пользовались: 6179 элементов.
 *
 * `url` — то, что кладётся в поле модели: корневой путь вида
 * `/upload/medialibrary/fca/....jpg`. Он же ключ, по которому файл узнаётся
 * повторно. `disk`/`path` заполняются только для файлов, которыми управляет
 * Storage; у перенесённых из Битрикса файл лежит прямо в public/ и колонки
 * пустые.
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

            // Списком листают по дате и фильтруют по типу и коллекции.
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
