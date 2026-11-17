<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\MediaCollection;
use Atrium\Admin\Models\MediaItem;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;

/**
 * The media library of the panel: uploading, listing and removing files.
 */
final class FileManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $next = 0;
        Str::createUuidsUsing(static function () use (&$next) {
            return Uuid::fromString(sprintf('00000000-0000-4000-8000-%012d', ++$next));
        });
        $this->travelTo('2026-11-17 12:00:00');
    }

    protected function tearDown(): void
    {
        Str::createUuidsNormally();

        parent::tearDown();
    }

    public function test_an_image_is_stored_under_a_new_name_and_added_to_the_library(): void
    {
        $response = $this->upload(UploadedFile::fake()->image('beach.jpg', 400, 300))->assertOk();

        $path = 'uploads/2026/11/00000000-0000-4000-8000-000000000001.jpg';
        $item = MediaItem::query()->sole();

        $response->assertExactJson([
            'url' => '/storage/'.$path,
            'path' => $path,
            'filename' => 'beach.jpg',
            'mime_type' => 'image/jpeg',
            'size' => $item->size,
            'extension' => 'jpg',
            'is_image' => true,
            'id' => $item->id,
        ]);

        Storage::disk('public')->assertExists($path);
        $this->assertSame('public', $item->disk);
        $this->assertSame($path, $item->path);
        $this->assertGreaterThan(0, $item->size);
        $this->assertSame('Uploaded in the admin panel', $item->collection->name);
        $this->assertSame(100, $item->collection->sort);
    }

    public function test_a_lighter_webp_copy_is_put_next_to_an_uploaded_image(): void
    {
        $this->upload(UploadedFile::fake()->image('beach.jpg', 400, 300))->assertOk();

        Storage::disk('public')->assertExists('uploads/2026/11/00000000-0000-4000-8000-000000000001.jpg.webp');
    }

    public function test_no_webp_copy_when_it_is_switched_off(): void
    {
        config()->set('atrium.uploads.webp', false);

        $this->upload(UploadedFile::fake()->image('beach.jpg', 400, 300))->assertOk();

        Storage::disk('public')->assertExists('uploads/2026/11/00000000-0000-4000-8000-000000000001.jpg');
        Storage::disk('public')->assertMissing('uploads/2026/11/00000000-0000-4000-8000-000000000001.jpg.webp');
    }

    public function test_a_document_is_kept_as_a_document_without_a_webp_copy(): void
    {
        $this->upload(UploadedFile::fake()->create('price list.pdf', 12, 'application/pdf'))
            ->assertOk()
            ->assertJsonPath('is_image', false)
            ->assertJsonPath('extension', 'pdf')
            ->assertJsonPath('filename', 'price list.pdf');

        Storage::disk('public')->assertExists('uploads/2026/11/00000000-0000-4000-8000-000000000001.pdf');
        Storage::disk('public')->assertMissing('uploads/2026/11/00000000-0000-4000-8000-000000000001.pdf.webp');
        $this->assertFalse(MediaItem::query()->sole()->is_image);
    }

    /**
     * @return array<string,array{0:string,1:string|null}>
     */
    public static function names(): array
    {
        return [
            'plain' => ['beach.jpg', 'jpg'],
            'upper case extension' => ['BEACH.JPG', 'jpg'],
            'jpeg' => ['beach.jpeg', 'jpeg'],
            'png' => ['logo.png', 'png'],
            'gif' => ['logo.gif', 'gif'],
            'webp' => ['logo.webp', 'webp'],
            'Word' => ['offer.docx', 'docx'],
            'Excel' => ['prices.xlsx', 'xlsx'],
            'PHP' => ['shell.php', null],
            'PHP behind a jpg' => ['photo.jpg.php', null],
            'HTML' => ['page.html', null],
            'SVG' => ['logo.svg', null],
            'no extension' => ['README', null],
            'only a dot' => ['beach.', null],
        ];
    }

    #[DataProvider('names')]
    public function test_only_the_allowed_types_are_kept(string $name, ?string $extension): void
    {
        $response = $this->upload(UploadedFile::fake()->create($name, 4));

        if ($extension !== null) {
            $response->assertOk()->assertJsonPath('extension', $extension);
            Storage::disk('public')->assertExists("uploads/2026/11/00000000-0000-4000-8000-000000000001.{$extension}");
        } else {
            $response->assertStatus(422)->assertExactJson([
                'message' => __('atrium::files.errors.type_not_allowed', ['types' => 'jpg, jpeg, png, gif, webp, pdf, doc, docx, xls, xlsx']),
            ]);
            $this->assertSame([], Storage::disk('public')->allFiles());
            $this->assertSame(0, MediaItem::query()->count());
        }
    }

    public function test_the_allowed_types_come_from_the_config(): void
    {
        config()->set('atrium.uploads.allowed_extensions', ['txt']);

        $this->upload(UploadedFile::fake()->create('notes.txt', 1))->assertOk();
        $this->upload(UploadedFile::fake()->create('beach.jpg', 1))
            ->assertStatus(422)
            ->assertJsonPath('message', __('atrium::files.errors.type_not_allowed', ['types' => 'txt']));
    }

    public function test_the_disk_and_directory_come_from_the_config(): void
    {
        Storage::fake('media');
        config()->set('atrium.uploads.disk', 'media');
        config()->set('atrium.uploads.directory', 'files');

        $this->upload(UploadedFile::fake()->create('offer.pdf', 1))
            ->assertOk()
            ->assertJsonPath('path', 'files/2026/11/00000000-0000-4000-8000-000000000001.pdf');

        Storage::disk('media')->assertExists('files/2026/11/00000000-0000-4000-8000-000000000001.pdf');
        $this->assertSame('media', MediaItem::query()->sole()->disk);
    }

    /**
     * @return array<string,array{0:int,1:bool}>
     */
    public static function sizes(): array
    {
        return [
            'one kilobyte' => [1, true],
            'exactly the limit' => [20, true],
            'one kilobyte over' => [21, false],
        ];
    }

    #[DataProvider('sizes')]
    public function test_files_over_the_size_limit_are_refused(int $kilobytes, bool $kept): void
    {
        config()->set('atrium.uploads.max_size', 20);

        $response = $this->upload(UploadedFile::fake()->create('offer.pdf', $kilobytes));

        if ($kept) {
            $response->assertOk();
        } else {
            $response->assertStatus(422)->assertJsonValidationErrors(['file' => __('atrium::files.errors.too_big', ['max' => 0.0])]);
            $this->assertSame(0, MediaItem::query()->count());
        }
    }

    public function test_the_size_limit_is_named_in_megabytes(): void
    {
        config()->set('atrium.uploads.max_size', 1536);

        $this->upload(UploadedFile::fake()->create('offer.pdf', 1537))
            ->assertJsonValidationErrors(['file' => 'The file is too large. Maximum size: 1.5 MB.']);
    }

    public function test_a_missing_or_broken_file_is_refused_by_name(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson('/admin/upload', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file' => __('atrium::files.errors.required')]);

        $this->postJson('/admin/upload', ['file' => 'not a file'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file' => __('atrium::files.errors.upload_broken')]);

        $this->assertSame(0, MediaItem::query()->count());
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function clientNames(): array
    {
        return [
            'Cyrillic and Chinese' => ['пляж 海滩.jpg', 'пляж 海滩.jpg'],
            'Arabic' => ['شاطئ.jpg', 'شاطئ.jpg'],
            'emoji' => ['🏖️.jpg', '🏖️.jpg'],
            'a path up the tree' => ['../../evil.jpg', 'evil.jpg'],
            'HTML' => ['<img src=x onerror=alert(1)>.jpg', '<img src=x onerror=alert(1)>.jpg'],
        ];
    }

    #[DataProvider('clientNames')]
    public function test_the_name_on_disk_never_comes_from_the_visitor(string $name, string $title): void
    {
        $this->upload(UploadedFile::fake()->create($name, 1))
            ->assertOk()
            ->assertJsonPath('path', 'uploads/2026/11/00000000-0000-4000-8000-000000000001.jpg')
            ->assertJsonPath('filename', $title);

        $this->assertSame(['uploads/2026/11/00000000-0000-4000-8000-000000000001.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_a_very_long_name_is_cut_to_250_characters(): void
    {
        $this->upload(UploadedFile::fake()->create(str_repeat('a', 300).'.pdf', 1))->assertOk();

        $this->assertSame(str_repeat('a', 250), MediaItem::query()->sole()->name);
    }

    public function test_every_upload_goes_to_one_collection_named_in_the_install_language(): void
    {
        $admin = $this->admin();
        $admin->update(['locale' => 'ru']);

        $this->upload(UploadedFile::fake()->create('one.pdf', 1), $admin)->assertOk();
        $this->upload(UploadedFile::fake()->create('two.pdf', 1), $admin)->assertOk();

        $this->assertSame(['Uploaded in the admin panel'], MediaCollection::query()->pluck('name')->all());
        $this->assertSame(2, MediaCollection::query()->sole()->items()->count());
    }

    public function test_uploading_needs_a_right_to_create_or_edit_something(): void
    {
        $this->upload(UploadedFile::fake()->create('one.pdf', 1), $this->editor(['posts.view']))->assertForbidden();
        $this->assertSame(0, MediaItem::query()->count());

        $this->upload(UploadedFile::fake()->create('one.pdf', 1), $this->editor(['posts.update'], 'writer@example.com'))->assertOk();
        $this->upload(UploadedFile::fake()->create('two.pdf', 1), $this->editor(['posts.create'], 'creator@example.com'))->assertOk();
        $this->upload(UploadedFile::fake()->create('three.pdf', 1), $this->editor(['labels.settings'], 'setup@example.com'))->assertOk();
        $this->assertSame(3, MediaItem::query()->count());
    }

    public function test_guests_cannot_upload(): void
    {
        $this->postJson('/admin/upload', ['file' => UploadedFile::fake()->create('one.pdf', 1)])->assertUnauthorized();
        $this->assertSame(0, MediaItem::query()->count());
    }

    public function test_the_list_pages_newest_first(): void
    {
        $collection = MediaCollection::query()->create(['name' => 'Photos']);

        foreach (range(1, 25) as $i) {
            $this->travelTo(now()->addMinute());
            $this->item($collection, "photo {$i}.jpg", true);
        }

        $first = $this->files()->assertOk();
        $first->assertJsonPath('total', 25)
            ->assertJsonPath('page', 1)
            ->assertJsonPath('per_page', 24)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(24, 'files')
            ->assertJsonPath('files.0.filename', 'photo 25.jpg')
            ->assertJsonPath('files.23.filename', 'photo 2.jpg');

        $this->files(['page' => 2])->assertJsonCount(1, 'files')->assertJsonPath('files.0.filename', 'photo 1.jpg');
        $this->files(['page' => 0])->assertJsonPath('page', 1)->assertJsonPath('files.0.filename', 'photo 25.jpg');
        $this->files(['page' => -3])->assertJsonPath('page', 1);
        $this->files(['page' => 3])->assertJsonCount(0, 'files')->assertJsonPath('total', 25);
    }

    public function test_files_of_the_same_moment_are_ordered_by_id(): void
    {
        $collection = MediaCollection::query()->create(['name' => 'Photos']);
        $this->item($collection, 'first.jpg', true);
        $this->item($collection, 'second.jpg', true);

        $this->files()->assertJsonPath('files.0.filename', 'second.jpg')->assertJsonPath('files.1.filename', 'first.jpg');
    }

    public function test_each_file_comes_with_what_the_fields_need(): void
    {
        $collection = MediaCollection::query()->create(['name' => 'Photos']);
        $item = $this->item($collection, 'beach.jpg', true);
        $migrated = $this->item($collection, 'old.jpg', true, ['path' => null, 'disk' => null, 'url' => '/upload/iblock/old.jpg']);

        $files = collect($this->files()->json('files'))->keyBy('id');

        $this->assertSame([
            'id' => $item->id,
            'url' => '/storage/beach.jpg',
            'path' => 'beach.jpg',
            'filename' => 'beach.jpg',
            'collection_id' => $collection->id,
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'is_image' => true,
        ], $files[$item->id]);
        $this->assertSame('upload/iblock/old.jpg', $files[$migrated->id]['path'], 'A migrated file without a path: from its address.');
    }

    public function test_the_list_is_filtered_by_kind_collection_and_text(): void
    {
        $photos = MediaCollection::query()->create(['name' => 'Photos', 'sort' => 2]);
        $papers = MediaCollection::query()->create(['name' => 'Papers', 'sort' => 1]);
        MediaCollection::query()->create(['name' => 'Empty', 'sort' => 0]);
        $this->item($photos, 'beach.jpg', true, ['keywords' => 'sea, sun']);
        $this->item($photos, 'scan.pdf', false, ['description' => 'Signed 100% copy']);
        $this->item($papers, 'contract.pdf', false);

        $names = fn (array $query): array => collect($this->files($query)->json('files'))->pluck('filename')->sort()->values()->all();

        $this->assertSame(['beach.jpg', 'contract.pdf', 'scan.pdf'], $names([]));
        $this->assertSame(['beach.jpg', 'contract.pdf', 'scan.pdf'], $names(['type' => 'all']));
        $this->assertSame(['beach.jpg'], $names(['type' => 'image']));
        $this->assertSame(['contract.pdf', 'scan.pdf'], $names(['type' => 'document']));
        $this->assertSame(['beach.jpg', 'scan.pdf'], $names(['collection' => $photos->id]));
        $this->assertSame(['scan.pdf'], $names(['collection' => $photos->id, 'type' => 'document']));
        $this->assertSame(['beach.jpg', 'contract.pdf', 'scan.pdf'], $names(['collection' => 0]));
        $this->assertSame(['beach.jpg'], $names(['search' => 'BEACH']));
        $this->assertSame(['beach.jpg'], $names(['search' => 'sun']));
        $this->assertSame([], $names(['search' => '0_%']));
        $this->assertSame(['beach.jpg', 'contract.pdf', 'scan.pdf'], $names(['search' => '   ']));

        $this->assertSame([
            ['id' => $papers->id, 'name' => 'Papers', 'count' => 1],
            ['id' => $photos->id, 'name' => 'Photos', 'count' => 2],
        ], $this->files()->json('collections'), 'Sorted, with counts; the empty one is left out.');
        $this->assertSame([['id' => $photos->id, 'name' => 'Photos', 'count' => 1]], $this->files(['type' => 'image'])->json('collections'));
        $this->assertSame([
            ['id' => $papers->id, 'name' => 'Papers', 'count' => 1],
            ['id' => $photos->id, 'name' => 'Photos', 'count' => 1],
        ], $this->files(['type' => 'document'])->json('collections'));
    }

    public function test_collections_of_the_same_order_are_sorted_by_name(): void
    {
        $b = MediaCollection::query()->create(['name' => 'Beta', 'sort' => 1]);
        $a = MediaCollection::query()->create(['name' => 'Alpha', 'sort' => 1]);
        $this->item($b, 'b.jpg', true);
        $this->item($a, 'a.jpg', true);

        $this->assertSame(['Alpha', 'Beta'], array_column($this->files()->json('collections'), 'name'));
    }

    public function test_the_list_needs_a_right_to_create_or_edit_something(): void
    {
        $this->actingAs($this->editor(['posts.view']), 'admin')->getJson('/admin/filemanager')->assertForbidden();
    }

    public function test_a_file_is_removed_by_id_from_the_library_and_the_disk(): void
    {
        $collection = MediaCollection::query()->create(['name' => 'Photos']);
        $item = $this->item($collection, 'beach.jpg', true);
        $other = $this->item($collection, 'other.jpg', true, ['path' => 'other.jpg']);
        Storage::disk('public')->put('beach.jpg', 'x');
        Storage::disk('public')->put('other.jpg', 'x');

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson('/admin/filemanager', ['id' => $item->id])
            ->assertOk()
            ->assertExactJson(['message' => __('atrium::files.removed')]);

        $this->assertNull(MediaItem::query()->find($item->id));
        Storage::disk('public')->assertMissing('beach.jpg');
        $this->assertNotNull(MediaItem::query()->find($other->id));
        Storage::disk('public')->assertExists('other.jpg');
    }

    public function test_a_file_is_removed_by_its_path(): void
    {
        $collection = MediaCollection::query()->create(['name' => 'Photos']);
        $this->item($collection, 'beach.jpg', true);
        $other = $this->item($collection, 'other.jpg', true, ['path' => 'other.jpg']);
        Storage::disk('public')->put('beach.jpg', 'x');

        $this->actingAs($this->admin(), 'admin')->deleteJson('/admin/filemanager', ['path' => 'beach.jpg'])->assertOk();

        $this->assertSame([$other->id], MediaItem::query()->pluck('id')->all());
        Storage::disk('public')->assertMissing('beach.jpg');
    }

    public function test_a_migrated_file_leaves_the_library_but_stays_on_disk(): void
    {
        $collection = MediaCollection::query()->create(['name' => 'Bitrix']);
        $item = $this->item($collection, 'old.jpg', true, ['disk' => null, 'path' => 'old.jpg']);
        Storage::disk('public')->put('old.jpg', 'x');

        $this->actingAs($this->admin(), 'admin')->deleteJson('/admin/filemanager', ['id' => $item->id])->assertOk();

        $this->assertSame(0, MediaItem::query()->count());
        Storage::disk('public')->assertExists('old.jpg');
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function unknownFiles(): array
    {
        return [
            'unknown id' => [['id' => 999]],
            'unknown path' => [['path' => 'nope.jpg']],
            'a path up the tree' => [['path' => '../.env']],
            'nothing at all' => [[]],
        ];
    }

    /**
     * @param  array<string,mixed>  $query
     */
    #[DataProvider('unknownFiles')]
    public function test_an_unknown_file_is_a_404_and_nothing_is_removed(array $query): void
    {
        $collection = MediaCollection::query()->create(['name' => 'Photos']);
        $this->item($collection, 'beach.jpg', true);
        Storage::disk('public')->put('beach.jpg', 'x');

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson('/admin/filemanager', $query)
            ->assertNotFound()
            ->assertExactJson(['message' => __('atrium::files.errors.not_found')]);

        $this->assertSame(1, MediaItem::query()->count());
        Storage::disk('public')->assertExists('beach.jpg');
    }

    public function test_removing_needs_a_right_to_create_or_edit_something(): void
    {
        $collection = MediaCollection::query()->create(['name' => 'Photos']);
        $item = $this->item($collection, 'beach.jpg', true);

        $this->actingAs($this->editor(['posts.view', 'posts.delete']), 'admin')
            ->deleteJson('/admin/filemanager', ['id' => $item->id])
            ->assertForbidden();

        $this->assertSame(1, MediaItem::query()->count());
    }

    private function upload(UploadedFile $file, ?AdminUser $user = null): TestResponse
    {
        return $this->actingAs($user ?? AdminUser::query()->first() ?? $this->admin(), 'admin')
            ->postJson('/admin/upload', ['file' => $file]);
    }

    /**
     * @param  array<string,mixed>  $query
     */
    private function files(array $query = []): TestResponse
    {
        return $this->actingAs(AdminUser::query()->first() ?? $this->admin(), 'admin')
            ->getJson('/admin/filemanager?'.http_build_query($query));
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function item(MediaCollection $collection, string $name, bool $image, array $attributes = []): MediaItem
    {
        return MediaItem::query()->create([
            'collection_id' => $collection->id,
            'name' => $name,
            'url' => '/storage/'.$name,
            'disk' => 'public',
            'path' => $name,
            'extension' => pathinfo($name, PATHINFO_EXTENSION),
            'mime_type' => $image ? 'image/jpeg' : 'application/pdf',
            'size' => 100,
            'is_image' => $image,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions, string $email = 'editor@example.com'): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => $email, 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role of '.$email, 'permissions' => $permissions]));

        return $user->fresh();
    }
}
