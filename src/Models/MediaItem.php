<?php

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Файл медиабиблиотеки.
 *
 * `url` — то, что попадает в поле модели, и он же ключ повторного
 * использования: один и тот же файл в библиотеке ровно один раз.
 */
class MediaItem extends Model
{
    protected $table = 'media_library_items';

    protected $fillable = [
        'bitrix_id',
        'collection_id',
        'name',
        'description',
        'keywords',
        'url',
        'disk',
        'path',
        'extension',
        'mime_type',
        'size',
        'is_image',
    ];

    protected $casts = [
        'bitrix_id' => 'integer',
        'collection_id' => 'integer',
        'size' => 'integer',
        'is_image' => 'boolean',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MediaCollection::class, 'collection_id');
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('is_image', true);
    }

    public function scopeDocuments(Builder $query): Builder
    {
        return $query->where('is_image', false);
    }

    /**
     * Поиск по имени и ключевым словам.
     *
     * Имена файлов после Битрикса — хеши, поэтому искать надо по названию
     * элемента («Мцхета.jpg») и ключевым словам, а не по имени на диске.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('keywords', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    /**
     * Физически удалить файл, если им управляет Storage.
     *
     * Файлы, перенесённые из Битрикса, лежат прямо в public/ и на них может
     * ссылаться контент, который мы не контролируем, — их с диска не трогаем,
     * только убираем из библиотеки.
     */
    public function deleteFile(): void
    {
        if ($this->disk === null || $this->path === null) {
            return;
        }

        Storage::disk($this->disk)->delete($this->path);
    }
}
