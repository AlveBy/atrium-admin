<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Models\File;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait HasAdminFiles
{
    /**
     * Get files relationship
     *
     * @return MorphMany<File, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    /**
     * Boot the trait
     */
    public static function bootHasAdminFiles(): void
    {
        static::deleting(function ($model) {
            // Delete associated files from storage
            foreach ($model->files as $file) {
                Storage::disk($file->disk)->delete($file->path);
                $file->delete();
            }
        });
    }

    /**
     * Get files by field name
     */
    public function getFilesByField(string $field): iterable
    {
        return $this->files()->where('field', $field)->orderBy('sort')->get();
    }

    /**
     * Get single file by field name
     */
    public function getFileByField(string $field): ?File
    {
        return $this->files()->where('field', $field)->first();
    }

    /**
     * Attach file to field
     */
    public function attachFile(string $field, string $path, array $metadata = []): File
    {
        $disk = config('atrium.uploads.disk', 'public');

        // Get file info
        $fullPath = Storage::disk($disk)->path($path);
        $filename = basename($path);
        $size = Storage::disk($disk)->size($path);
        $mimeType = Storage::disk($disk)->mimeType($path);

        // Determine sort order
        $maxSort = $this->files()->where('field', $field)->max('sort') ?? 0;

        return $this->files()->create([
            'field' => $field,
            'disk' => $disk,
            'path' => $path,
            'filename' => $metadata['filename'] ?? $filename,
            'original_filename' => $metadata['original_filename'] ?? $filename,
            'mime_type' => $mimeType,
            'size' => $size,
            'sort' => $maxSort + 1,
            'metadata' => $metadata['metadata'] ?? null,
        ]);
    }

    /**
     * Detach file from field
     */
    public function detachFile(string $field, int $fileId): void
    {
        $file = $this->files()->where('field', $field)->where('id', $fileId)->first();

        if ($file) {
            Storage::disk($file->disk)->delete($file->path);
            $file->delete();
        }
    }

    /**
     * Reorder files for field: the new order whole, or the old one.
     */
    public function reorderFiles(string $field, array $fileIds): void
    {
        DB::transaction(function () use ($field, $fileIds): void {
            foreach ($fileIds as $index => $fileId) {
                $this->files()
                    ->where('field', $field)
                    ->where('id', $fileId)
                    ->update(['sort' => $index + 1]);
            }
        }, attempts: 3);
    }

    /**
     * Sync files for field (replace all)
     */
    public function syncFiles(string $field, array $paths, array $metadata = []): void
    {
        // Delete existing files
        $existingFiles = $this->getFilesByField($field);
        foreach ($existingFiles as $file) {
            Storage::disk($file->disk)->delete($file->path);
            $file->delete();
        }

        // Attach new files
        foreach ($paths as $index => $path) {
            $this->attachFile($field, $path, [
                'sort' => $index + 1,
                ...$metadata,
            ]);
        }
    }

    /**
     * Get file URL by field
     */
    public function getFileUrl(string $field): ?string
    {
        $file = $this->getFileByField($field);

        if (! $file) {
            return null;
        }

        return Storage::disk($file->disk)->url($file->path);
    }

    /**
     * Get all file URLs for field
     */
    public function getFileUrls(string $field): array
    {
        $files = $this->getFilesByField($field);
        $urls = [];

        foreach ($files as $file) {
            $urls[] = [
                'id' => $file->id,
                'url' => Storage::disk($file->disk)->url($file->path),
                'filename' => $file->filename,
                'size' => $file->size,
                'mime_type' => $file->mime_type,
            ];
        }

        return $urls;
    }

    /**
     * Check if field has files
     */
    public function hasFile(string $field): bool
    {
        return $this->files()->where('field', $field)->exists();
    }

    /**
     * Get files count for field
     */
    public function getFilesCount(string $field): int
    {
        return $this->files()->where('field', $field)->count();
    }
}
