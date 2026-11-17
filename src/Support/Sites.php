<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Http\Middleware\EnterSiteFolder;
use Atrium\Admin\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The sites of the panel and the one the current request is about.
 *
 * On the public side the site comes from the domain and the folder (see
 * forRequest()); in the admin it is the
 * one picked in the header; an API client names it with ?site=code. Until
 * something sets it (a console command, a queued job) there is no current
 * site: records of every site are visible, new ones go to the default site.
 */
final class Sites
{
    /** @var Collection<int,Site>|null */
    private ?Collection $all = null;

    private ?int $currentId = null;

    private bool $resolved = false;

    /** @var list<int>|null */
    private ?array $allowedIds = null;

    /**
     * Every site, active or not, the default one first.
     *
     * @return Collection<int,Site>
     */
    public function all(): Collection
    {
        return $this->all ??= $this->load();
    }

    public function default(): Site
    {
        return $this->all()->firstWhere('is_default', true) ?? $this->all()->first();
    }

    public function find(mixed $id): ?Site
    {
        return $this->all()->first(fn (Site $site): bool => (string) $site->id === (string) $id);
    }

    public function findByCode(string $code): ?Site
    {
        return $this->all()->firstWhere('code', $code);
    }

    /**
     * The active site at the root of the host: the one that claims it and
     * sits in no folder; the default site otherwise, so localhost and a
     * fresh server's IP open something.
     */
    public function forHost(string $host): Site
    {
        return $this->all()->first(fn (Site $site): bool => $site->active && $site->folder === null && $site->hasDomain($host))
            ?? $this->default();
    }

    /**
     * The site a public address belongs to: the active site living on the
     * host whose folder is the longest one the path starts with (/site2
     * holds /site2/… but not /site22), else the site at the root of the
     * host. A site lives on the domains it lists, one with no domains on
     * every domain; at the same folder the one that names the host wins.
     */
    public function forRequest(string $host, string $path): Site
    {
        $site = $this->folderSites($host)->first(fn (Site $site): bool => $site->holdsPath($path));

        return $site ?? $this->forHost($host);
    }

    /**
     * The active sites in folders of a host, one per folder, the longest
     * folder first — the order they are matched in.
     *
     * @return Collection<int,Site>
     */
    public function folderSites(string $host): Collection
    {
        return $this->all()
            ->filter(fn (Site $site): bool => $site->active && $site->folder !== null && ($site->hasDomain($host) || (array) $site->domains === []))
            ->sortBy([
                fn (Site $a, Site $b): int => strlen((string) $b->folder) <=> strlen((string) $a->folder),
                fn (Site $a, Site $b): int => (int) $b->hasDomain($host) <=> (int) $a->hasDomain($host),
            ])
            ->unique('folder')
            ->values();
    }

    /**
     * The root of the application on the request's host, without the folder
     * of the site the request is in: http://example.com, not …/site2.
     */
    public static function rootUrl(Request $request): string
    {
        $root = $request->root();
        $folder = $request->attributes->get(EnterSiteFolder::FOLDER);

        return is_string($folder) && str_ends_with($root, '/'.$folder)
            ? substr($root, 0, -strlen('/'.$folder))
            : $root;
    }

    public function current(): Site
    {
        return ($this->resolved ? $this->find($this->currentId) : null)
            ?? ($this->allowedIds === null ? null : $this->find($this->allowedIds[0]))
            ?? $this->default();
    }

    /**
     * Keeps a request to the sites its administrator may work with: records
     * of other sites are hidden even when no site is picked, and new ones go
     * to the first allowed site.
     *
     * @param  Collection<int,Site>  $sites
     */
    public function limitTo(Collection $sites): void
    {
        $this->allowedIds = $sites->map(fn (Site $site): int => $site->id)->values()->all();
    }

    /**
     * The sites a request is kept to, or null when it may see every site.
     *
     * @return list<int>|null
     */
    public function allowedIds(): ?array
    {
        return $this->allowedIds;
    }

    /**
     * Whether a request has said which site it is about. Only then are
     * records of other sites hidden.
     */
    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function setCurrent(Site $site): void
    {
        $this->currentId = $site->id;
        $this->resolved = true;

        app(ContentLanguages::class)->flush();
    }

    public function forgetCurrent(): void
    {
        $this->currentId = null;
        $this->resolved = false;
        $this->allowedIds = null;

        app(ContentLanguages::class)->flush();
    }

    public function isMultisite(): bool
    {
        return $this->all()->count() > 1;
    }

    public function flush(): void
    {
        $this->all = null;
    }

    /**
     * @return Collection<int,Site>
     */
    private function load(): Collection
    {
        try {
            $sites = Site::query()->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->get();
        } catch (QueryException) {
            $sites = new Collection;
        }

        if ($sites->isEmpty()) {
            $sites = new Collection([new Site(['code' => 'main', 'name' => (string) config('app.name', 'Site'), 'active' => true, 'is_default' => true])]);
        }

        return $sites;
    }
}
