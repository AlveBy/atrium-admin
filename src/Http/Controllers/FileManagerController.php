<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Models\MediaCollection;
use Atrium\Admin\Models\MediaItem;
use Atrium\Admin\Support\WebpCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Медиабиблиотека: список, загрузка, удаление.
 *
 * Раньше список собирался обходом каталога uploads на диске. Из-за этого
 * менеджер видел только то, что загрузили через админку, — а все семь тысяч
 * картинок, переехавших из Битрикса, лежат в public/upload и в список не
 * попадали. Теперь источник — таблица media_library_items, куда попадает и
 * перенесённое из Битрикса, и всё, что загружают сейчас. Заодно появился
 * поиск по названию: имена файлов на диске — хеши, искать по ним нечего.
 */
class FileManagerController extends Controller
{
    private const PER_PAGE = 24;

    /**
     * Загрузить файл и сразу положить его в библиотеку.
     *
     * Регистрация здесь — то, ради чего всё затевалось: загруженная один раз
     * картинка дальше выбирается из библиотеки, а не заливается заново.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.config('atrium.uploads.max_size', 10240),
            ],
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
                'message' => 'Недопустимый тип файла. Разрешены: '.implode(', ', $allowed),
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
            'url' => '/storage/'.ltrim($path, '/'),
            'disk' => $disk,
            'path' => $path,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'is_image' => str_starts_with($mimeType, 'image/'),
        ]);

        // Ключи url/path/filename сохранены как были: на них завязаны поля
        // с картинками и вставка изображения в визуальном редакторе.
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
     * Положить рядом с загруженной картинкой её облегчённую копию.
     *
     * Посетителю она поедет вместо оригинала, если браузер понимает webp
     * (подменяет веб-сервер, правило в public/.htaccess). Раньше копии делала
     * только команда `images:webp` по расписанию, и картинка, загруженная в
     * админке, до её запуска ехала тяжёлой.
     *
     * Отказ проглатываем: не сделать копию — досадно, а не дать сохранить
     * картинку из-за этого — куда хуже. Причина уходит в журнал, а команда
     * по расписанию потом всё равно догонит пропущенное.
     */
    private function makeWebpCopy(string $disk, string $path): void
    {
        if (! config('atrium.uploads.webp', true) || ! WebpCopy::convertible($path)) {
            return;
        }

        try {
            WebpCopy::make(Storage::disk($disk)->path($path));
        } catch (\Throwable $e) {
            Log::warning('Не удалось сделать webp-копию для '.$path.': '.$e->getMessage());
        }
    }

    /**
     * Список файлов библиотеки.
     */
    public function index(Request $request): JsonResponse
    {
        $type = (string) $request->get('type', 'all');
        $page = max(1, (int) $request->get('page', 1));

        $query = MediaItem::query();

        if ($type === 'image') {
            $query->images();
        } elseif ($type === 'document') {
            $query->documents();
        }

        if ($collection = (int) $request->get('collection', 0)) {
            $query->where('collection_id', $collection);
        }

        $query->search((string) $request->get('search', ''));

        $total = (clone $query)->count();

        $files = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, self::PER_PAGE)
            ->get()
            ->map(fn (MediaItem $item) => [
                'id' => $item->id,
                'url' => $item->url,
                // Историческое имя ключа: его читают поля и визуальный редактор.
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
     * Убрать файл из библиотеки.
     */
    public function destroy(Request $request): JsonResponse
    {
        $item = MediaItem::query()
            ->when($request->filled('id'), fn ($q) => $q->whereKey($request->integer('id')))
            ->when(! $request->filled('id'), fn ($q) => $q->where('path', $request->string('path')))
            ->first();

        if ($item === null) {
            return response()->json(['message' => 'Файл не найден.'], 404);
        }

        // Файл с диска стираем, только если им управляем мы. Перенесённые из
        // Битрикса лежат в public/upload и могут быть вставлены в тексты
        // страниц напрямую — удаление оставило бы битые картинки в контенте.
        $item->deleteFile();
        $item->delete();

        return response()->json(['message' => 'Файл удалён из библиотеки.']);
    }

    /**
     * Коллекции с числом файлов — для фильтра в шапке менеджера.
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
                'count' => (int) $collection->items_count,
            ])
            ->values()
            ->all();
    }

    /** Куда складывать то, что загружают через админку. */
    private function uploadsCollection(): MediaCollection
    {
        return MediaCollection::firstOrCreate(
            ['name' => 'Загруженные из админки'],
            ['sort' => 100]
        );
    }
}
