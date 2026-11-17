<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\MediaCollection;
use Atrium\Admin\Models\MediaItem;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * atrium:media:index: files that are already on disk get into the media
 * library, once.
 */
final class IndexMediaTest extends TestCase
{
    private string $public;

    protected function setUp(): void
    {
        parent::setUp();

        // A public/ of its own: the real one is shared.
        $this->public = sys_get_temp_dir().'/atrium-media-public-'.getmypid().'-'.uniqid();
        mkdir($this->public, 0777, true);
        $this->app->usePublicPath($this->public);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->public);

        parent::tearDown();
    }

    public function test_uploads_on_the_disk_are_registered_once(): void
    {
        $disk = Storage::fake('public');
        $disk->put('uploads/photo.JPG', 'jpeg-bytes');
        $disk->put('uploads/2026/price list.pdf', '%PDF-1.4 price list');
        $disk->put('uploads/README', 'no extension');
        $disk->put('elsewhere/skipped.png', 'png');

        $this->artisan('atrium:media:index')->expectsOutput('Added: 2')->assertSuccessful();

        $photo = MediaItem::query()->where('name', 'photo.JPG')->sole();
        $this->assertSame('/storage/uploads/photo.JPG', $photo->url);
        $this->assertSame('public', $photo->disk);
        $this->assertSame('uploads/photo.JPG', $photo->path);
        $this->assertSame('jpg', $photo->extension);
        $this->assertSame(10, (int) $photo->size);
        $this->assertTrue((bool) $photo->is_image);
        $this->assertNull($photo->collection_id);

        $pdf = MediaItem::query()->where('name', 'price list.pdf')->sole();
        $this->assertSame('/storage/uploads/2026/price list.pdf', $pdf->url);
        $this->assertFalse((bool) $pdf->is_image);
        $this->assertSame('application/pdf', $pdf->mime_type);

        $this->artisan('atrium:media:index')->expectsOutput('Added: 0')->assertSuccessful();
        $this->assertSame(2, MediaItem::query()->count());
    }

    public function test_the_disk_and_directory_come_from_the_config(): void
    {
        config(['atrium.uploads.disk' => 'media', 'atrium.uploads.directory' => 'files']);
        Storage::fake('media')->put('files/logo.svg', '<svg/>');
        Storage::fake('public')->put('uploads/ignored.png', 'png');

        $this->artisan('atrium:media:index')->expectsOutput('Added: 1')->assertSuccessful();

        $item = MediaItem::query()->sole();
        $this->assertSame(['media', 'files/logo.svg', '/storage/files/logo.svg'], [$item->disk, $item->path, $item->url]);
        $this->assertTrue((bool) $item->is_image);
    }

    public function test_no_upload_folder_means_nothing_to_add(): void
    {
        Storage::fake('public')->put('other/file.txt', 'x');

        $this->artisan('atrium:media:index')->expectsOutput('No files found.')->assertSuccessful();

        $this->assertSame(0, MediaItem::query()->count());
    }

    public function test_a_dry_run_lists_and_adds_nothing(): void
    {
        $disk = Storage::fake('public');
        $disk->put('uploads/a.png', 'png');
        $disk->put('uploads/b.png', 'png');
        MediaItem::query()->create(['name' => 'a.png', 'url' => '/storage/uploads/a.png', 'disk' => 'public', 'path' => 'uploads/a.png', 'extension' => 'png', 'size' => 3, 'is_image' => true]);

        $this->artisan('atrium:media:index', ['--dry-run' => true])
            ->expectsOutput('  /storage/uploads/b.png')
            ->doesntExpectOutput('  /storage/uploads/a.png')
            ->expectsOutput('Would be added: 1')
            ->assertSuccessful();

        $this->assertSame(['/storage/uploads/a.png'], MediaItem::query()->pluck('url')->all());
    }

    public function test_files_go_into_the_named_collection(): void
    {
        Storage::fake('public')->put('uploads/a.png', 'png');
        $existing = MediaCollection::query()->create(['name' => 'Migrated']);

        $this->artisan('atrium:media:index', ['--collection' => 'Migrated'])->assertSuccessful();

        $this->assertSame($existing->id, MediaItem::query()->sole()->collection_id);
        $this->assertSame(1, MediaCollection::query()->count());

        Storage::disk('public')->put('uploads/b.png', 'png');
        $this->artisan('atrium:media:index', ['--collection' => 'Новые'])->assertSuccessful();
        $this->assertSame('Новые', MediaItem::query()->where('name', 'b.png')->sole()->collection?->name);
    }

    public function test_a_folder_in_public_is_registered_without_a_disk(): void
    {
        mkdir($this->public.'/old/img/deep', 0777, true);
        file_put_contents($this->public.'/old/img/banner.webp', 'webp-bytes');
        file_put_contents($this->public.'/old/img/deep/terms.txt', 'Terms');
        file_put_contents($this->public.'/old/img/LICENSE', 'no extension');

        $this->artisan('atrium:media:index', ['--path' => '/old/img/'])->expectsOutput('Added: 2')->assertSuccessful();

        $banner = MediaItem::query()->where('name', 'banner.webp')->sole();
        $this->assertSame('/old/img/banner.webp', $banner->url);
        $this->assertNull($banner->disk);
        $this->assertNull($banner->path);
        $this->assertSame(10, (int) $banner->size);
        $this->assertTrue((bool) $banner->is_image);

        $terms = MediaItem::query()->where('name', 'terms.txt')->sole();
        $this->assertSame('/old/img/deep/terms.txt', $terms->url);
        $this->assertSame('text/plain', $terms->mime_type);
    }

    public function test_a_missing_public_folder_is_named_and_fails(): void
    {
        $this->artisan('atrium:media:index', ['--path' => 'nowhere'])
            ->expectsOutput('Folder does not exist: '.$this->public.'/nowhere')
            ->doesntExpectOutput('No files found.')
            ->assertFailed();

        $this->assertSame(0, MediaItem::query()->count());
    }
}
