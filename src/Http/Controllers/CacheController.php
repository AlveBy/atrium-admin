<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Cache\CacheStore;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Contracts\Cacheable;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Cache" section: how well the record cache works, and clearing it
 * for one module, one site or everything. For superadmins.
 */
class CacheController extends Controller
{
    public function __construct(
        private readonly RecordCache $cache,
        private readonly CacheStore $store,
        private readonly ApiResources $resources,
        private readonly Sites $sites,
    ) {}

    public function index(): Response
    {
        $totals = $this->cache->totals();
        $reads = $totals['hits'] + $totals['misses'];

        return Inertia::render('Admin/Cache/Index', [
            'status' => [
                'enabled' => (bool) config('atrium.cache.enabled', true),
                'pages' => (bool) config('atrium.cache.pages.enabled', false),
                'driver' => $this->store->driver(),
                'tags' => $this->store->usesTags(),
                'hits' => $totals['hits'],
                'misses' => $totals['misses'],
                'rate' => $reads > 0 ? round($totals['hits'] * 100 / $reads, 1) : null,
                ...$this->store->size(),
            ],
            'modules' => collect($this->modules())
                ->map(fn (string $class, string $key): array => ['key' => $key, 'label' => $this->resources->label($key)])
                ->sortBy('label')
                ->values(),
            'sites' => $this->sites->all()
                ->filter(fn (Site $site): bool => $site->exists)
                ->map(fn (Site $site): array => ['id' => $site->getKey(), 'name' => $site->name])
                ->values(),
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => ['required', Rule::in(['all', 'module', 'site', 'stats'])],
            'module' => ['required_if:scope,module', 'nullable', Rule::in(array_keys($this->modules()))],
            'site' => ['required_if:scope,site', 'nullable', 'integer'],
        ]);

        match ($validated['scope']) {
            'module' => $this->cache->flushClass($this->modules()[$validated['module']]),
            'site' => $this->cache->flushSite((int) $validated['site']),
            'stats' => $this->cache->resetTotals(),
            default => $this->cache->flushAll(),
        };

        return redirect()->route('admin.cache.index')->with('success', __('atrium::cache.cleared'));
    }

    /**
     * @return array<string,class-string> key => model class of every cached module
     */
    private function modules(): array
    {
        return array_filter($this->resources->modules(), static fn (string $class): bool => is_subclass_of($class, Cacheable::class));
    }
}
