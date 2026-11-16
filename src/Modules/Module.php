<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Modules;

use Illuminate\Support\Str;

/**
 * One module package, as its composer.json describes it:
 *
 *     "type": "atrium-module",
 *     "extra": {
 *         "atrium": {
 *             "resources": ["Vendor\\Reviews\\Review"],
 *             "public_urls": {"Vendor\\Reviews\\Review": {"prefix": "reviews"}},
 *             "migrations": "database/migrations",
 *             "demo": "Vendor\\Reviews\\Database\\ReviewsDemoSeeder",
 *             "settings": "Vendor\\Reviews\\ReviewsSettings",
 *             "permissions": {
 *                 "moderate": "atrium-reviews::permissions.moderate",
 *                 "reviews.export": {"label": "Export reviews", "with": "reviews.view"}
 *             }
 *         }
 *     }
 *
 * Every key is optional. The modules it works with but does not need are its
 * Composer `suggest` list: their demo content is filled in first.
 *
 * The module's key ("reviews") names its settings (`reviews.per_page`) and
 * its permissions; it is the package name without the vendor and the
 * "atrium-" prefix unless the manifest sets `key`. `title` is its name in
 * the panel, text or a translation key.
 *
 * `permissions` are what the module checks beyond view, create, update and
 * delete: "{section}.{action}", or a bare action for "{key}.{action}". Each
 * has a label (text or a translation key) and may name a permission it comes
 * `with`: the roles that have that one get it when the module is installed.
 */
final class Module
{
    /**
     * @param  array<int,class-string>  $resources  models the panel shows
     * @param  array<class-string,array<string,string>>  $publicUrls  pages on the site, as in `atrium.public_urls`
     * @param  array<int,string>  $migrations  absolute paths
     * @param  class-string|null  $demo  a seeder that fills the module with demo content
     * @param  array<int,string>  $suggests  package names
     * @param  class-string<ModuleSettings>|null  $settings  what can be set for it in the panel
     * @param  array<string,array{label:string,with:string|null}>  $declaredPermissions  its own permissions by name
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly array $resources = [],
        public readonly array $publicUrls = [],
        public readonly array $migrations = [],
        public readonly ?string $demo = null,
        public readonly array $suggests = [],
        public readonly ?string $settings = null,
        public readonly string $key = '',
        public readonly string $title = '',
        public readonly array $declaredPermissions = [],
    ) {}

    /**
     * The key of a package name: `alveby/atrium-shop-delivery` → `shop_delivery`.
     */
    public static function keyOf(string $name): string
    {
        $base = str_contains($name, '/') ? substr($name, strrpos($name, '/') + 1) : $name;
        $base = str_starts_with($base, 'atrium-') ? substr($base, strlen('atrium-')) : $base;

        return (string) preg_replace('/[^a-z0-9]+/', '_', strtolower($base));
    }

    /**
     * The key, or the one derived from the name when none was given.
     */
    public function key(): string
    {
        return $this->key !== '' ? $this->key : self::keyOf($this->name);
    }

    /**
     * The permissions the module brings besides those of its models'
     * sections: "{key}.settings" for a module with settings, and the ones
     * its manifest declares.
     *
     * @return array<int,string>
     */
    public function permissions(): array
    {
        return [
            ...($this->settings === null ? [] : [$this->key().'.settings']),
            ...array_keys($this->declaredPermissions),
        ];
    }

    /**
     * The name shown in the panel, translated.
     */
    public function label(): string
    {
        return $this->title !== '' ? (string) __($this->title) : Str::headline($this->key());
    }

    /**
     * Read the package in this folder; null when it is not a module.
     */
    public static function fromPath(string $path): ?self
    {
        $file = rtrim($path, '/').'/composer.json';
        $composer = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (! is_array($composer) || ($composer['type'] ?? null) !== Modules::TYPE || ! is_string($composer['name'] ?? null)) {
            return null;
        }

        return self::fromComposer($composer, $path);
    }

    /**
     * @param  array<string,mixed>  $composer
     */
    public static function fromComposer(array $composer, string $path): self
    {
        $manifest = (array) ($composer['extra']['atrium'] ?? []);
        $root = rtrim($path, '/');
        $name = strtolower((string) $composer['name']);
        $key = isset($manifest['key']) ? self::keyOf((string) $manifest['key']) : '';

        return new self(
            name: $name,
            description: (string) ($composer['description'] ?? ''),
            resources: array_values(array_map('strval', (array) ($manifest['resources'] ?? []))),
            publicUrls: (array) ($manifest['public_urls'] ?? []),
            migrations: array_map(static fn (string $dir): string => $root.'/'.trim($dir, '/'), array_map('strval', (array) ($manifest['migrations'] ?? []))),
            demo: isset($manifest['demo']) ? (string) $manifest['demo'] : null,
            suggests: array_map('strtolower', array_keys((array) ($composer['suggest'] ?? []))),
            settings: isset($manifest['settings']) ? (string) $manifest['settings'] : null,
            key: $key,
            title: (string) ($manifest['title'] ?? ''),
            declaredPermissions: self::permissionsOf((array) ($manifest['permissions'] ?? []), $key !== '' ? $key : self::keyOf($name)),
        );
    }

    /**
     * The manifest's `permissions`, by full name: a bare action belongs to
     * the module's own key. Names that are not "section.action" are skipped.
     *
     * @param  array<array-key,mixed>  $declared
     * @return array<string,array{label:string,with:string|null}>
     */
    private static function permissionsOf(array $declared, string $key): array
    {
        $permissions = [];

        foreach ($declared as $name => $spec) {
            $name = str_contains((string) $name, '.') ? (string) $name : $key.'.'.$name;

            if (preg_match('/^[a-z0-9_-]+\.[a-z0-9_-]+$/', $name) !== 1) {
                continue;
            }

            $spec = is_array($spec) ? $spec : ['label' => $spec];
            $permissions[$name] = [
                'label' => (string) ($spec['label'] ?? ''),
                'with' => isset($spec['with']) ? (string) $spec['with'] : null,
            ];
        }

        return $permissions;
    }
}
