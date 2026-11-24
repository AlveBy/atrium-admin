<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Modules;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The values of the modules' settings (Atrium\Admin\Modules\ModuleSettings),
 * kept in `admin_module_settings`. Read them with the facade:
 *
 *     Setting::get('shop.currency');          // the current site's, for a per-site module
 *     Setting::get('shop.currency', 'EUR');   // with a fallback
 *     Setting::all('shop');                   // every value of the module
 *
 * The key is the module's key and the field's name. A value nobody saved, or
 * saved empty, is the field's default(); a module that is not installed has
 * no settings. Each module's values are read once per request.
 */
final class Settings
{
    public const TABLE = 'admin_module_settings';

    /** @var array<string,array<int,array<string,mixed>>> module key => site id (0: every site) => values */
    private array $stored = [];

    /** @var array<string,ModuleSettings|null> */
    private array $declarations = [];

    public function __construct(
        private readonly Modules $modules,
        private readonly Sites $sites,
    ) {}

    /**
     * One value: "{module key}.{field}".
     */
    public function get(string $key, mixed $default = null, Site|int|null $site = null): mixed
    {
        $parts = explode('.', $key, 2);

        return $this->all($parts[0], $site)[$parts[1] ?? ''] ?? $default;
    }

    /**
     * Every value of a module, defaults filled in.
     *
     * @return array<string,mixed>
     */
    public function all(string $module, Site|int|null $site = null): array
    {
        $declaration = $this->declaration($module);

        if ($declaration === null) {
            return [];
        }

        $stored = array_filter($this->stored($module, $this->siteId($declaration, $site)), static fn (mixed $value): bool => $value !== null && $value !== '');

        foreach ($declaration->secrets() as $name) {
            if (isset($stored[$name])) {
                $stored[$name] = $this->reveal((string) $stored[$name]);
            }
        }

        return array_replace($declaration->defaults(), array_filter($stored, static fn (mixed $value): bool => $value !== null));
    }

    /**
     * What is saved for the module and site, without the defaults.
     *
     * @return array<string,mixed>
     */
    public function stored(string $module, int $siteId = 0): array
    {
        if (! isset($this->stored[$module])) {
            try {
                // Kept in the record cache: public pages read settings on
                // every request. Not migrated yet throws past remember(), so
                // an empty answer is never kept.
                $this->stored[$module] = app(RecordCache::class)->remember(self::class, 'settings:'.$module, static function () use ($module): array {
                    $stored = [];

                    foreach (DB::table(self::TABLE)->where('module', $module)->get(['site_id', 'values']) as $row) {
                        $stored[(int) $row->site_id] = (array) json_decode((string) $row->values, true);
                    }

                    return $stored;
                }, perSite: false);
            } catch (QueryException) {
                // Not migrated yet: nothing is saved.
                $this->stored[$module] = [];
            }
        }

        return $this->stored[$module][$siteId] ?? [];
    }

    /**
     * Save values over the stored ones. For a per-site module, those of the
     * given site (the current one when none is given).
     *
     * @param  array<string,mixed>  $values
     */
    public function save(string $module, array $values, Site|int|null $site = null): void
    {
        $declaration = $this->declaration($module);
        $siteId = $declaration === null ? 0 : $this->siteId($declaration, $site);
        foreach ($declaration?->secrets() ?? [] as $name) {
            if (isset($values[$name]) && $values[$name] !== '') {
                $values[$name] = Crypt::encryptString((string) $values[$name]);
            }
        }

        $values = [...$this->stored($module, $siteId), ...$values];
        $now = now();

        $updated = DB::table(self::TABLE)
            ->where('module', $module)
            ->when($siteId === 0, static fn ($query) => $query->whereNull('site_id'), static fn ($query) => $query->where('site_id', $siteId))
            ->update(['values' => json_encode($values, JSON_UNESCAPED_UNICODE), 'updated_at' => $now]);

        if ($updated === 0) {
            DB::table(self::TABLE)->insert([
                'module' => $module,
                'site_id' => $siteId === 0 ? null : $siteId,
                'values' => json_encode($values, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->stored[$module][$siteId] = $values;
        app(RecordCache::class)->flushClass(self::class);
    }

    /**
     * A secret as it was saved, or null when it cannot be read: the
     * application key changed since.
     */
    private function reveal(string $encrypted): ?string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * The settings an installed module declares, or null.
     */
    public function declaration(string $module): ?ModuleSettings
    {
        if (array_key_exists($module, $this->declarations)) {
            return $this->declarations[$module];
        }

        $found = $this->modules->findByKey($module);
        $class = $found !== null && $this->modules->isInstalled($found->name) ? $found->settings : null;
        $declaration = $class !== null && is_subclass_of($class, ModuleSettings::class) ? app($class) : null;

        return $this->declarations[$module] = $declaration;
    }

    /**
     * The site whose values are used: 0 for a module with one set of values.
     */
    public function siteId(ModuleSettings $declaration, Site|int|null $site = null): int
    {
        if (! $declaration->perSite) {
            return 0;
        }

        return match (true) {
            $site instanceof Site => (int) $site->id,
            is_int($site) => $site,
            default => (int) $this->sites->current()->id,
        };
    }

    /**
     * Forget what was read: a module installed or removed, or a test.
     */
    public function flush(): void
    {
        $this->stored = $this->declarations = [];

        if (app()->resolved(RecordCache::class)) {
            app(RecordCache::class)->flushClass(self::class);
        }
    }
}
