<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Models\MediaCollection;
use Atrium\Admin\Models\MediaItem;
use Atrium\Admin\Support\CdnPurge;
use Atrium\Admin\Support\MediaUrl;
use Atrium\Admin\Support\WebpCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * Media library: list, upload, delete.
 *
 * The list used to be built by walking the uploads directory on disk, so the
 * manager saw only what had been uploaded through the panel — thousands of
 * images migrated from Bitrix live in public/upload and never showed up. Now
 * the source is the media_library_items table, which holds both migrated files
 * and everything uploaded since. That also enabled search by title: file names
 * on disk are hashes, there is nothing to search in them.
 */
class FileManagerController extends Controller
{
    /**
     * Formats finfo names only by the container they are built on.
     */
    private const CONTAINERS = [
        'application/zip' => ['docx', 'xlsx'],
        'application/x-ole-storage' => ['doc', 'xls'],
    ];

    /**
     * What makes an SVG run something when it is opened: a script, an event
     * handler, a javascript: link, embedded HTML, or entities that could
     * spell any of these.
     */
    private const ACTIVE_SVG = '/<script|<foreignObject|\son[a-z]+\s*=|javascript:|&#|<!ENTITY/i';

    private const PER_PAGE = 24;

    /**
     * Upload a file and add it to the library right away.
     *
     * Registering it here is the whole point: an image uploaded once is then
     * picked from the library instead of being uploaded again.
     */
    public function upload(Request $request): JsonResponse
    {
        $maxKb = config('atrium.uploads.max_size', 10240);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
            ],
        ], [
            'file.required' => __('atrium::files.errors.required'),
            'file.file' => __('atrium::files.errors.upload_broken'),
            'file.max' => __('atrium::files.errors.too_big', ['max' => round($maxKb / 1024, 1)]),
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $allowed = config('atrium.uploads.allowed_extensions', [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx',
        ]);

        if (! in_array($extension, $allowed, true)) {
            return response()->json([
                'message' => __('atrium::files.errors.type_not_allowed', ['types' => implode(', ', $allowed)]),
            ], 422);
        }

        if (! $this->isWhatItsNameSays($file, $extension)) {
            return response()->json([
                'message' => __('atrium::files.errors.content_mismatch', ['type' => $extension]),
            ], 422);
        }

        $disk = config('atrium.uploads.disk', 'public');
        $directory = config('atrium.uploads.directory', 'uploads').'/'.date('Y/m');

        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, $disk);

        $this->makeWebpCopy($disk, $path);

        $mimeType = (string) $file->getMimeType();
        $item = MediaItem::create([
            'collection_id' => $this->uploadsCollection()->id,
            'name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'url' => MediaUrl::forStored($disk, $path),
            'disk' => $disk,
            'path' => $path,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'is_image' => str_starts_with($mimeType, 'image/'),
        ]);

        // The url/path/filename keys are kept as they were: image fields and
        // image insertion in the visual editor depend on them.
        return response()->json([
            'url' => $item->url,
            'path' => $path,
            'filename' => $item->name,
            'mime_type' => $item->mime_type,
            'size' => $item->size,
            'extension' => $item->extension,
            'is_image' => $item->is_image,
            'id' => $item->id,
        ]);
    }

    /**
     * Whether the file is what its name says, judged by its content (finfo),
     * not by the name the client sent: a script called photo.jpg is refused.
     * Office files finfo knows only by their container — a zip, an OLE store —
     * count as the formats built on it. An SVG may carry a script, so one with
     * anything that runs is refused.
     */
    private function isWhatItsNameSays(UploadedFile $file, string $extension): bool
    {
        $types = MimeTypes::getDefault();
        $type = $types->guessMimeType($file->getRealPath()) ?? '';
        $extensions = [...$types->getExtensions($type), ...self::CONTAINERS[$type] ?? []];

        return in_array($extension, $extensions, true)
            && ($type !== 'image/svg+xml' || preg_match(self::ACTIVE_SVG, (string) $file->getContent()) === 0);
    }

    /**
     * Put a lighter copy next to an uploaded image.
     *
     * Visitors get it instead of the original if the browser understands webp
     * (the web server swaps it, rule in public/.htaccess). Copies used to be
     * made only by the scheduled `images:webp` command, so an image uploaded in
     * the panel was served heavy until the next run.
     *
     * WebpCopy::make() reports what it could not do instead of throwing: a
     * missing copy is a pity, refusing to save the image because of it would be
     * far worse.
     */
    private function makeWebpCopy(string $disk, string $path): void
    {
        // Only for a disk on this server: the swap is the web server's.
        // WebpCopy::make() skips what it does not compress.
        if (! config('atrium.uploads.webp', true) || config("filesystems.disks.{$disk}.driver") !== 'local') {
            return;
        }

        WebpCopy::make(Storage::disk($disk)->path($path));
    }

    /**
     * Library file list.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type');
        $type = is_string($type) ? $type : 'all';
        $page = max(1, $request->integer('page'));

        $query = MediaItem::query();

        if ($type === 'image') {
            $query->images();
        } elseif ($type === 'document') {
            $query->documents();
        }

        if ($collection = $request->integer('collection')) {
            $query->where('collection_id', $collection);
        }

        $query->search((string) $request->get('search', ''));

        $total = $query->count();

        $files = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, self::PER_PAGE)
            ->get()
            ->map(fn (MediaItem $item) => [
                'id' => $item->id,
                'url' => $item->url,
                // Historical key name: fields and the visual editor read it.
                'path' => $item->path ?? ltrim($item->url, '/'),
                'filename' => $item->name,
                'collection_id' => $item->collection_id,
                'extension' => $item->extension,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'is_image' => $item->is_image,
            ]);

        return response()->json([
            'files' => $files,
            'collections' => $this->collections($type),
            'total' => $total,
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'last_page' => (int) ceil($total / self::PER_PAGE),
        ]);
    }

    /**
     * Remove a file from the library.
     */
    public function destroy(Request $request): JsonResponse
    {
        $item = MediaItem::query()
            ->when($request->filled('id'), fn ($q) => $q->whereKey($request->integer('id')))
            ->when(! $request->filled('id'), fn ($q) => $q->where('path', $request->string('path')))
            ->first();

        if ($item === null) {
            return response()->json(['message' => __('atrium::files.errors.not_found')], 404);
        }

        // The file is erased from disk only if we manage it. Files migrated from
        // Bitrix live in public/upload and may be embedded in page texts
        // directly — deleting them would leave broken images in the content.
        // Addresses on the CDN, taken while the record still says where it was.
        $cached = $item->disk !== null && $item->path !== null ? CdnPurge::addressesOf($item->url) : [];

        $item->deleteFile();
        $item->delete();

        $this->purgeFromCdn($cached);

        return response()->json(['message' => __('atrium::files.removed')]);
    }

    /**
     * Without a purge the CDN keeps serving a deleted file until its cache
     * expires. After the response, and a CDN that refuses only gets logged:
     * the file is gone from the site either way.
     *
     * @param  array<int,string>  $urls
     */
    private function purgeFromCdn(array $urls): void
    {
        if (! CdnPurge::configured() || ! config('atrium.cdn.purge.on_delete', true)) {
            return;
        }

        defer(static function () use ($urls): void {
            try {
                CdnPurge::urls($urls);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Collections with file counts — for the filter in the manager header.
     *
     * @return array<int,array{id:int,name:string,count:int}>
     */
    private function collections(string $type): array
    {
        return MediaCollection::query()
            ->withCount(['items' => function ($query) use ($type): void {
                if ($type === 'image') {
                    $query->where('is_image', true);
                } elseif ($type === 'document') {
                    $query->where('is_image', false);
                }
            }])
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->filter(fn (MediaCollection $collection) => $collection->items_count > 0)
            ->map(fn (MediaCollection $collection) => [
                'id' => $collection->id,
                'name' => $collection->name,
                'count' => $collection->items_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Where files uploaded through the panel go.
     *
     * The collection is found by name, so the name is taken in the install's
     * default language, not the current admin's — otherwise every language
     * would get its own collection.
     */
    private function uploadsCollection(): MediaCollection
    {
        return MediaCollection::firstOrCreate(
            ['name' => __('atrium::files.uploads_collection', [], (string) config('atrium.locale', 'en'))],
            ['sort' => 100]
        );
    }
}
