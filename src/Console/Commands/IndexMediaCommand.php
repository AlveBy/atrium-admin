<?php

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Models\MediaCollection;
use Atrium\Admin\Models\MediaItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Register files that already sit on disk in the media library.
 *
 * Needed once when switching to the library: before it, uploads just landed in
 * a directory, bypassing the database, so the list does not show them. From
 * then on registration happens by itself on upload through the panel.
 *
 * It can also be pointed at any folder inside public — for example, to add
 * images that came along with migrated content.
 */
class IndexMediaCommand extends Command
{
    protected $signature = 'atrium:media:index
                            {--path= : Folder inside public/ (defaults to the uploads directory)}
                            {--collection= : Collection to put the files in}
                            {--dry-run : Show what would be added}';

    protected $description = 'Add files already on disk to the media library';

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $relative = (string) ($this->option('path') ?? '');

        [$files, $prefix] = $relative === ''
            ? $this->storageFiles()
            : $this->publicFiles($relative);

        if ($files === []) {
            $this->warn('No files found.');

            return self::SUCCESS;
        }

        $known = MediaItem::query()->pluck('id', 'url');
        $collection = $this->collection();
        $added = 0;

        foreach ($files as $file) {
            $url = $prefix.'/'.ltrim($file['relative'], '/');

            if (isset($known[$url])) {
                continue;
            }

            if ($dryRun) {
                $this->line('  '.$url);
                $added++;

                continue;
            }

            $extension = strtolower(pathinfo($file['relative'], PATHINFO_EXTENSION));

            MediaItem::create([
                'collection_id' => $collection?->id,
                'name' => basename($file['relative']),
                'url' => $url,
                'disk' => $file['disk'],
                'path' => $file['disk'] === null ? null : $file['relative'],
                'extension' => $extension,
                'mime_type' => $file['mime'],
                'size' => $file['size'],
                'is_image' => in_array($extension, self::IMAGE_EXTENSIONS, true),
            ]);

            $added++;
        }

        $this->info(sprintf('%s: %d', $dryRun ? 'Would be added' : 'Added', $added));

        return self::SUCCESS;
    }

    /**
     * @return array{0:array<int,array{relative:string,disk:?string,mime:?string,size:int}>,1:string}
     */
    private function storageFiles(): array
    {
        $disk = (string) config('atrium.uploads.disk', 'public');
        $directory = (string) config('atrium.uploads.directory', 'uploads');
        $storage = Storage::disk($disk);

        if (! $storage->exists($directory)) {
            return [[], '/storage'];
        }

        $files = [];

        foreach ($storage->allFiles($directory) as $path) {
            if (pathinfo($path, PATHINFO_EXTENSION) === '') {
                continue;
            }

            $files[] = [
                'relative' => $path,
                'disk' => $disk,
                'mime' => $storage->mimeType($path) ?: null,
                'size' => $storage->size($path),
            ];
        }

        return [$files, '/storage'];
    }

    /**
     * @return array{0:array<int,array{relative:string,disk:?string,mime:?string,size:int}>,1:string}
     */
    private function publicFiles(string $relative): array
    {
        $relative = trim($relative, '/');
        $root = public_path($relative);

        if (! is_dir($root)) {
            $this->error('Folder does not exist: '.$root);

            return [[], ''];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() === '') {
                continue;
            }

            $files[] = [
                'relative' => ltrim(str_replace($root, '', $file->getPathname()), '/'),
                'disk' => null,
                'mime' => mime_content_type($file->getPathname()) ?: null,
                'size' => $file->getSize(),
            ];
        }

        return [$files, '/'.$relative];
    }

    private function collection(): ?MediaCollection
    {
        $name = (string) ($this->option('collection') ?? '');

        return $name === '' ? null : MediaCollection::firstOrCreate(['name' => $name]);
    }
}
