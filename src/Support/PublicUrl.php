<?php

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Публичный адрес страницы — тот, что показывается под «Символьным кодом».
 *
 * Раньше превью собиралось из ЧПУ админки (`getFullPath()`), а это разные
 * вещи: у раздела в базе лежит код `china`, публичный же адрес —
 * `/countries/china`. Новому разделу и подавно неоткуда взять `fullPath`, и
 * форма показывала `https://example.com/japan` — адрес, которого нет.
 *
 * Карта адресов лежит в `config/atrium.php` → `public_urls`: пакет не
 * знает ни про `/countries`, ни про `/catalog`, это роуты проекта. Модели,
 * которой в карте нет (заявки, баннеры, отзывы), превью не положено вовсе —
 * страницы у неё просто не существует.
 */
final class PublicUrl
{
    /**
     * Сколько уровней вложенности разделов разбираем, поднимаясь к корню.
     * Ограничение спасает от зацикливания, если раздел окажется собственным
     * предком.
     */
    private const MAX_DEPTH = 20;

    /** @var array<string,array<int|string,string>> */
    private static array $pathCache = [];

    /**
     * Дописать полю «Символьный код» всё, что нужно для честного превью.
     *
     * @param  array<string,mixed>  $field
     * @return array<string,mixed>
     */
    public static function decorateSlugField(array $field, string $modelClass): array
    {
        if (($field['type'] ?? null) !== 'slug') {
            return $field;
        }

        $rule = self::ruleFor($modelClass);

        if ($rule === null) {
            // Публичной страницы нет — молчим, а не показываем выдуманный адрес.
            $field['showPreview'] = false;

            return $field;
        }

        $field['publicPrefix'] = $rule['prefix'];
        $field['parentField'] = $rule['parent'];
        $field['parentPaths'] = $rule['parent'] === null
            ? []
            : self::sectionPaths($rule['tree'], $rule['parent_column']);

        return $field;
    }

    /**
     * Правило для модели: префикс роута, поле формы с разделом и дерево,
     * из которого считается путь раздела.
     *
     * @return array{prefix:string,parent:?string,tree:?string,parent_column:string}|null
     */
    public static function ruleFor(string $modelClass): ?array
    {
        $rule = config('atrium.public_urls.'.$modelClass);

        if (! is_array($rule)) {
            return null;
        }

        $tree = Arr::get($rule, 'tree');
        $parent = Arr::get($rule, 'parent');

        return [
            'prefix' => trim((string) Arr::get($rule, 'prefix', ''), '/'),
            'parent' => $tree ? $parent : null,
            'tree' => $tree,
            'parent_column' => (string) Arr::get($rule, 'parent_column', 'parent_id'),
        ];
    }

    /**
     * Путь каждого раздела дерева: id => «китай/лечебные-туры».
     *
     * Один запрос на всё дерево — предки собираются в памяти. Тянуть их
     * связями значило бы запрос на каждого предка каждого раздела.
     *
     * @return array<int|string,string>
     */
    public static function sectionPaths(string $treeClass, string $parentColumn = 'parent_id'): array
    {
        $cacheKey = $treeClass.'|'.$parentColumn;

        if (isset(self::$pathCache[$cacheKey])) {
            return self::$pathCache[$cacheKey];
        }

        $rows = $treeClass::query()
            ->get(['id', $parentColumn, 'code'])
            ->keyBy('id');

        $paths = [];

        foreach ($rows as $id => $row) {
            $parts = [];
            $cursor = $row;

            for ($depth = 0; $cursor !== null && $depth < self::MAX_DEPTH; $depth++) {
                if (($code = (string) $cursor->code) !== '') {
                    array_unshift($parts, $code);
                }

                $parentId = $cursor->{$parentColumn};
                $cursor = $parentId ? ($rows[$parentId] ?? null) : null;
            }

            $paths[$id] = implode('/', $parts);
        }

        return self::$pathCache[$cacheKey] = $paths;
    }

    /**
     * Адрес записи на сайте — тот же, что показывает превью под «Символьным
     * кодом»: префикс, путь раздела, код. Null, если публичной страницы нет
     * или код пустой.
     */
    public static function forRecord(Model $record): ?string
    {
        $rule = self::ruleFor($record::class);
        $code = (string) $record->getAttribute('code');

        if ($rule === null || $code === '') {
            return null;
        }

        $parentPath = $rule['parent'] === null
            ? ''
            : (self::sectionPaths($rule['tree'], $rule['parent_column'])[$record->getAttribute($rule['parent'])] ?? '');

        return url(implode('/', array_filter([$rule['prefix'], $parentPath, $code], static fn (string $part): bool => $part !== '')));
    }

    /**
     * Сбросить память о путях разделов (нужно тестам и после переноса данных).
     */
    public static function forgetPaths(): void
    {
        self::$pathCache = [];
    }
}
