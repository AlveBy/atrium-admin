<?php

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Public address of a page — the one shown under the slug field.
 *
 * The preview used to be built from the admin slug path (`getFullPath()`),
 * and those are different things: a section stores the code `china`, while
 * its public address is `/countries/china`. A new section has no `fullPath`
 * at all, and the form showed `https://example.com/japan` — an address that
 * does not exist.
 *
 * The address map lives in `config/atrium.php` → `public_urls`: the package
 * knows nothing about `/countries` or `/catalog`, those are project routes.
 * A model missing from the map (form submissions, banners, reviews) gets no
 * preview at all — it simply has no page.
 */
final class PublicUrl
{
    /**
     * How many section levels to walk up towards the root. The limit guards
     * against a loop if a section turns out to be its own ancestor.
     */
    private const MAX_DEPTH = 20;

    /** @var array<string,array<int|string,string>> */
    private static array $pathCache = [];

    /**
     * Add to the slug field everything an honest preview needs.
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
            // No public page — show nothing rather than a made-up address.
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
     * Rule for a model: route prefix, the form field holding the section, and
     * the tree the section path is computed from.
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
     * Path of every section in the tree: id => "china/spa-tours".
     *
     * One query for the whole tree — ancestors are assembled in memory.
     * Loading them through relations would mean a query per ancestor of every
     * section.
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
     * Address of the record on the site — the same one the slug field preview
     * shows: the site's domain and folder, prefix, section path, code. Null
     * if there is no public page or the code is empty.
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

        $sites = app(Sites::class);
        $site = $sites->find($record->getAttributes()['site_id'] ?? null) ?? $sites->current();

        return $site->url(implode('/', array_filter([$rule['prefix'], $parentPath, $code], static fn (string $part): bool => $part !== '')));
    }

    /**
     * Forget the cached section paths (for tests and after a data import).
     */
    public static function forgetPaths(): void
    {
        self::$pathCache = [];
    }
}
