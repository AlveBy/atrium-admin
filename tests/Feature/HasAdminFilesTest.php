<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\File;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

final class HasAdminFilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('docs');
    }

    private function article(): Handbook
    {
        return Handbook::query()->create(['name' => 'Chair']);
    }

    public function test_an_attached_file_records_what_the_disk_knows_about_it(): void
    {
        Storage::disk('public')->put('articles/photo.txt', 'twelve bytes');
        $article = $this->article();

        $file = $article->attachFile('photos', 'articles/photo.txt', ['original_filename' => 'Фото стула.txt', 'metadata' => ['alt' => 'A chair']]);

        $file->refresh();
        $this->assertSame('photos', $file->field);
        $this->assertSame('public', $file->disk);
        $this->assertSame('articles/photo.txt', $file->path);
        $this->assertSame('photo.txt', $file->filename);
        $this->assertSame('Фото стула.txt', $file->original_filename);
        $this->assertSame('text/plain', $file->mime_type);
        $this->assertSame(12, $file->size);
        $this->assertSame(1, $file->sort);
        $this->assertSame(['alt' => 'A chair'], $file->metadata);
        $this->assertTrue($file->fileable?->is($article));
    }

    public function test_attached_files_line_up_after_the_last_one_of_their_field(): void
    {
        Storage::disk('public')->put('a.txt', 'a');
        Storage::disk('public')->put('b.txt', 'b');
        $article = $this->article();

        $first = $article->attachFile('photos', 'a.txt');
        $second = $article->attachFile('photos', 'b.txt', ['filename' => 'renamed.txt']);
        $other = $article->attachFile('documents', 'a.txt');

        $this->assertSame([1, 2, 1], [$first->sort, $second->sort, $other->sort]);
        $this->assertSame('renamed.txt', $second->filename);
        $this->assertSame('b.txt', $second->original_filename);
        $this->assertNull($first->metadata);
    }

    public function test_the_disk_comes_from_the_config(): void
    {
        config()->set('atrium.uploads.disk', 'docs');
        Storage::disk('docs')->put('manual.txt', 'manual');

        $file = $this->article()->attachFile('documents', 'manual.txt');

        $this->assertSame('docs', $file->disk);
        $this->assertSame(Storage::disk('docs')->url('manual.txt'), $file->url);
    }

    public function test_files_of_a_field_are_listed_in_their_order(): void
    {
        Storage::disk('public')->put('a.txt', 'a');
        Storage::disk('public')->put('b.txt', 'bb');
        $article = $this->article();
        $a = $article->attachFile('photos', 'a.txt');
        $b = $article->attachFile('photos', 'b.txt');
        $article->attachFile('documents', 'a.txt');

        $article->reorderFiles('photos', [$b->id, $a->id]);

        $this->assertSame([$b->id, $a->id], collect($article->getFilesByField('photos'))->pluck('id')->all());
        $this->assertSame(2, $article->getFilesCount('photos'));
        $this->assertSame(1, $article->getFilesCount('documents'));
        $this->assertTrue($article->hasFile('photos'));
        $this->assertFalse($article->hasFile('videos'));
        $this->assertNull($article->getFileByField('videos'));
        $this->assertSame(0, $article->getFilesCount('videos'));
        $this->assertSame([
            ['id' => $b->id, 'url' => Storage::disk('public')->url('b.txt'), 'filename' => 'b.txt', 'size' => 2, 'mime_type' => 'text/plain'],
            ['id' => $a->id, 'url' => Storage::disk('public')->url('a.txt'), 'filename' => 'a.txt', 'size' => 1, 'mime_type' => 'text/plain'],
        ], $article->getFileUrls('photos'));
        $this->assertSame([], $article->getFileUrls('videos'));
    }

    public function test_reordering_leaves_other_fields_alone(): void
    {
        Storage::disk('public')->put('a.txt', 'a');
        $article = $this->article();
        $photo = $article->attachFile('photos', 'a.txt');
        $document = $article->attachFile('documents', 'a.txt');

        $article->reorderFiles('photos', [5 => $document->id, 6 => $photo->id]);

        $this->assertSame(7, $photo->fresh()?->sort);
        $this->assertSame(1, $document->fresh()?->sort);
    }

    public function test_the_url_of_a_field_is_its_first_file(): void
    {
        Storage::disk('public')->put('a.txt', 'a');
        $article = $this->article();

        $this->assertNull($article->getFileUrl('photos'));

        $article->attachFile('photos', 'a.txt');
        $this->assertSame(Storage::disk('public')->url('a.txt'), $article->getFileUrl('photos'));
    }

    public function test_detaching_a_file_deletes_it_from_the_disk(): void
    {
        Storage::disk('public')->put('a.txt', 'a');
        Storage::disk('public')->put('b.txt', 'b');
        $article = $this->article();
        $a = $article->attachFile('photos', 'a.txt');
        $b = $article->attachFile('photos', 'b.txt');

        $article->detachFile('photos', $a->id);
        // Another field, or a file of another record: nothing happens.
        $article->detachFile('documents', $b->id);
        $this->article()->detachFile('photos', $b->id);

        Storage::disk('public')->assertMissing('a.txt');
        Storage::disk('public')->assertExists('b.txt');
        $this->assertSame([$b->id], File::query()->pluck('id')->all());
    }

    public function test_syncing_replaces_the_files_of_a_field(): void
    {
        foreach (['old.txt', 'keep.txt', 'new-1.txt', 'new-2.txt'] as $path) {
            Storage::disk('public')->put($path, $path);
        }
        $article = $this->article();
        $article->attachFile('photos', 'old.txt');
        $article->attachFile('documents', 'keep.txt');

        $article->syncFiles('photos', ['new-1.txt', 'new-2.txt'], ['metadata' => ['source' => 'import']]);

        Storage::disk('public')->assertMissing('old.txt');
        Storage::disk('public')->assertExists('keep.txt');
        $photos = collect($article->getFilesByField('photos'));
        $this->assertSame(['new-1.txt', 'new-2.txt'], $photos->pluck('path')->all());
        $this->assertSame([1, 2], $photos->pluck('sort')->all());
        $this->assertSame([['source' => 'import'], ['source' => 'import']], $photos->pluck('metadata')->all());
        $this->assertSame(1, $article->getFilesCount('documents'));
    }

    public function test_deleting_the_record_deletes_its_files(): void
    {
        Storage::disk('public')->put('a.txt', 'a');
        Storage::disk('public')->put('b.txt', 'b');
        $article = $this->article();
        $other = $this->article();
        $article->attachFile('photos', 'a.txt');
        $other->attachFile('photos', 'b.txt');

        $article->delete();

        Storage::disk('public')->assertMissing('a.txt');
        Storage::disk('public')->assertExists('b.txt');
        $this->assertSame(1, File::query()->count());
    }

    public function test_a_file_knows_its_url_path_and_whether_it_is_on_the_disk(): void
    {
        Storage::disk('public')->put('a.txt', 'a');
        $file = $this->article()->attachFile('photos', 'a.txt');

        $this->assertSame(Storage::disk('public')->url('a.txt'), $file->url);
        $this->assertSame(Storage::disk('public')->path('a.txt'), $file->full_path);
        $this->assertTrue($file->exists());

        Storage::disk('public')->delete('a.txt');
        $this->assertFalse($file->exists());
    }

    public function test_deleting_a_file_row_deletes_the_file(): void
    {
        Storage::disk('public')->put('a.txt', 'a');
        $file = $this->article()->attachFile('photos', 'a.txt');

        $file->delete();

        Storage::disk('public')->assertMissing('a.txt');
    }

    /**
     * @return array<string,array{int,string}>
     */
    public static function sizes(): array
    {
        return [
            'nothing' => [0, '0 B'],
            'bytes' => [512, '512 B'],
            'exactly a kilobyte stays in bytes' => [1024, '1024 B'],
            'one past a kilobyte' => [1025, '1 KB'],
            'kilobytes' => [1536, '1.5 KB'],
            'two decimals' => [1234567, '1.18 MB'],
            'megabytes' => [5 * 1024 * 1024 + 1, '5 MB'],
            'gigabytes' => [3 * 1024 ** 3 + 1, '3 GB'],
            'terabytes stay in gigabytes' => [2 * 1024 ** 4, '2048 GB'],
        ];
    }

    #[DataProvider('sizes')]
    public function test_a_size_is_shown_in_the_largest_fitting_unit(int $bytes, string $shown): void
    {
        $this->assertSame($shown, (new File(['size' => $bytes]))->human_size);
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function types(): array
    {
        return [
            'JPEG' => ['image/jpeg', true],
            'SVG' => ['image/svg+xml', true],
            'PDF' => ['application/pdf', false],
            'text that mentions an image' => ['text/image', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('types')]
    public function test_an_image_is_told_by_its_mime_type(string $mime, bool $image): void
    {
        $this->assertSame($image, (new File(['mime_type' => $mime]))->isImage());
    }
}
