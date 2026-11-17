<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Cache;

use Atrium\Admin\Contracts\Cacheable;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The record cache: lists answer with ids, every record comes from a card
 * built once from its id, and a page of twenty records with forty
 * properties each costs one multi-get of the cache instead of hundreds of
 * queries.
 *
 *     $page = Product::query()->where('section_id', 3)->orderBy('price')->cachedPaginate(20);
 *     $cards = Product::cachedMany([12, 7, 31]);
 *
 * Keys carry version counters (see Versions):
 *
 *     all            everything; "clear all"
 *     site:{id}      one site's cards, lists and pages
 *     list:{class}   any record of the class saved or deleted: its lists,
 *                    and the cards of other classes that show it (a brand's
 *                    name on its products) rebuild lazily
 *     card:{class}   the class's cards; "clear this module"
 *
 * Saving a record drops its own cards in every language and site at once,
 * and bumps its class's list version.
 */
final class RecordCache
{
    /** @var array<string,Closure(Collection<int,Model>,string):array<int|string,mixed>> */
    private array $extenders = [];

    private ?Closure $thumbnails = null;

    private ?CardBuilder $builder = null;

    private int $bulk = 0;

    /** @var array<string,string> class => class: the classes saved during a bulk, as a set */
    private array $bulkTouched = [];

    private bool $bulkEverything = false;

    /** @var array<int,Closure(string):iterable<string>> */
    private array $warmers = [];

    /** @var array<string,string> name => name: the version names the current request has read through, as a set */
    private array $read = [];

    /** @var array{hits:int,misses:int} */
    private array $stats = ['hits' => 0, 'misses' => 0];

    /** @var array<string,array<int|string,true>> class => keys of the records being changed right now */
    private array $changing = [];

    public function __construct(
        private readonly CacheStore $store,
        private readonly Versions $versions,
    ) {}

    /**
     * The read name of a request whose page must not be kept: it holds
     * something made for one visitor only.
     */
    public const NO_PAGE = 'no-page';

    /** @var array{hits:int,misses:int} the counts of the request reset() last closed */
    private array $lastStats = ['hits' => 0, 'misses' => 0];

    public function enabled(): bool
    {
        return (bool) config('atrium.cache.enabled', true);
    }

    public function store(): CacheStore
    {
        return $this->store;
    }

    public function versions(): Versions
    {
        return $this->versions;
    }

    /**
     * Cards of records in the order of the ids; ids with no record are
     * left out. One multi-get, then one batch of queries for the misses.
     *
     * @param  class-string<Model>  $class
     * @param  iterable<int|string>  $ids
     * @return array<int,Card>
     */
    public function cards(string $class, iterable $ids, ?string $locale = null): array
    {
        $ids = array_unique(array_map(self::normalizeId(...), is_array($ids) ? $ids : iterator_to_array($ids, false)));

        if ($ids === []) {
            return [];
        }

        $locale ??= app()->getLocale();
        $site = $this->site();
        // A cached page that shows the record is stale once any record of
        // its class is saved (cards are dropped one by one, not versioned).
        $this->markRead(['list:'.$class]);

        if (! $this->enabled() || ! is_subclass_of($class, Cacheable::class)) {
            $data = $this->builder()->build($class, $ids, $locale, $site);

            return $this->ordered($ids, $data);
        }

        $prefix = $this->cardPrefix($class, $site, $locale);
        $keys = array_map(static fn (int|string $id): string => $prefix.$id, $ids);
        $values = $this->store->many(self::group($class), $keys);
        $data = [];
        $missing = [];

        foreach ($ids as $i => $id) {
            $value = $values[$keys[$i]] ?? null;

            if (is_array($value)) {
                $data[$id] = $value;
            } else {
                $missing[] = $id;
            }
        }

        $this->stats['hits'] += count($data);
        $this->stats['misses'] += count($missing);

        if ($missing !== []) {
            $built = $this->builder()->build($class, $missing, $locale, $site);
            $put = [];

            foreach ($built as $id => $card) {
                $data[$id] = $card;
                $put[$prefix.$id] = $card;
            }

            $this->store->putMany(self::group($class), $put, $this->ttl('cards'));
        }

        return $this->ordered($ids, $data);
    }

    /**
     * The ids a list query answers, cached by the query itself (its
     * filters, order, page, the site and the language) until a record of
     * its class changes.
     *
     * @param  Builder<Model>  $query
     * @param  array<int,class-string<Model>>  $dependsOn  other classes the query reads (a join, whereHas)
     */
    public function ids(Builder $query, ?int $perPage = null, ?int $page = null, array $dependsOn = []): CachedIds
    {
        $model = $query->getModel();
        $class = $model::class;

        $run = static function () use ($query, $model, $perPage, $page): array {
            $key = $model->getQualifiedKeyName();

            if ($perPage === null) {
                $ids = $query->toBase()->pluck($key)->all();

                return ['ids' => $ids, 'total' => count($ids)];
            }

            $total = $query->toBase()->getCountForPagination();
            $ids = $total > 0 ? (clone $query)->forPage((int) $page, $perPage)->toBase()->pluck($key)->all() : [];

            return ['ids' => $ids, 'total' => $total];
        };

        if (! $this->enabled()) {
            $result = $run();

            return new CachedIds(array_map(self::normalizeId(...), $result['ids']), (int) $result['total']);
        }

        $names = ['all', 'site:'.$this->site(), 'list:'.$class];

        foreach ($dependsOn as $dependency) {
            $names[] = 'list:'.$dependency;
        }

        $this->markRead($names);

        $hash = hash('xxh128', implode("\n", [
            $query->toSql(),
            json_encode(array_map(self::binding(...), $query->getBindings())),
            $perPage,
            $page,
            app()->getLocale(),
        ]));
        $key = 'ids:'.$this->versions->stamp($names).':'.$hash;
        $cached = $this->store->get(self::group($class), $key);

        if (is_array($cached)) {
            $this->stats['hits']++;
        } else {
            $this->stats['misses']++;
            $cached = $run();
            $this->store->put(self::group($class), $key, $cached, $this->ttl('lists'));
        }

        return new CachedIds(array_map(self::normalizeId(...), $cached['ids']), (int) $cached['total']);
    }

    /**
     * A page of cards with the paginator the views know.
     *
     * @param  Builder<Model>  $query
     * @param  array<int,class-string<Model>>  $dependsOn
     * @return LengthAwarePaginator<int,Card>
     */
    public function paginate(Builder $query, int $perPage = 20, string $pageName = 'page', ?int $page = null, ?string $locale = null, array $dependsOn = []): LengthAwarePaginator
    {
        $page ??= Paginator::resolveCurrentPage($pageName);
        $result = $this->ids($query, $perPage, $page, $dependsOn);

        return new LengthAwarePaginator(
            $this->cards($query->getModel()::class, $result->ids, $locale),
            $result->total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName],
        );
    }

    /**
     * Anything else worth keeping until a record of the given classes
     * changes: a tree of sections, the counters of a block.
     *
     * @param  string|array<int,string>  $classes  model classes, or any name a writer drops with flushClass()
     */
    public function remember(string|array $classes, string $key, Closure $callback, ?int $ttl = null, bool $perSite = true): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }

        $classes = (array) $classes;
        $names = ['all', ...array_map(static fn (string $class): string => 'list:'.$class, $classes)];

        if ($perSite) {
            $names[] = 'site:'.$this->site();
        }

        $this->markRead($names);
        $group = self::group($classes[0] ?? 'remember');
        $full = 'r:'.$this->versions->stamp($names).':'.($perSite ? $this->site().':' : '').$key;
        $cached = $this->store->get($group, $full);

        if (is_array($cached) && array_key_exists('v', $cached)) {
            $this->stats['hits']++;

            return $cached['v'];
        }

        $this->stats['misses']++;
        $value = $callback();
        $this->store->put($group, $full, ['v' => $value], $ttl ?? $this->ttl('lists'));

        return $value;
    }

    /**
     * A record was saved, deleted or restored: drop its cards in every
     * language and site, make its class's lists and the cards that show it
     * stale, and pass the news to the records it belongs to.
     */
    public function changed(Model $record): void
    {
        $class = $record::class;

        if (in_array($class, (array) config('atrium.cache.ignore', []), true)) {
            return;
        }

        if ($this->bulk > 0) {
            $this->bulkTouched[$class] = $class;

            return;
        }

        $key = $record->getKey();

        if ($this->changing[$class][$key] ?? false) {
            return;
        }

        $this->changing[$class][$key] = true;

        try {
            $this->versions->bump('list:'.$class);

            // A site's domains and languages are in every address of its
            // cards; a language is in every site's.
            if ($record instanceof Site && $record->getKey() !== null) {
                $this->flushSite((int) $record->getKey());
            } elseif ($record instanceof Language) {
                $this->flushAll();
            }

            if ($record instanceof Cacheable && $record->getKey() !== null) {
                $this->forgetCards($class, [$record->getKey(), ...$this->builder()->referencing($record)]);
            }

            if (method_exists($record, 'cacheTouches')) {
                foreach ((array) $record->cacheTouches() as $owner) {
                    if ($owner instanceof Model) {
                        $this->changed($owner);
                    }
                }
            }
        } catch (Throwable $e) {
            // A cache that is down must not fail the save.
            report($e);
        } finally {
            unset($this->changing[$class][$key]);
        }
    }

    /**
     * Drop a class's cards and lists (and those of the classes showing it),
     * or the remember() values kept under a name of one's own.
     *
     * @param  string  $class  a model class or a remember() name
     */
    public function flushClass(string $class): void
    {
        $this->versions->bump('card:'.$class, 'list:'.$class);
        $this->store->flush(self::group($class));
    }

    public function flushSite(int $site): void
    {
        $this->versions->bump('site:'.$site);
    }

    public function flushAll(): void
    {
        $this->versions->bump('all');
        $this->store->flush();
    }

    /**
     * Run an import without dropping cards record by record, then drop
     * the classes it touched once.
     *
     * @template T
     *
     * Pass $everything for an import that writes past Eloquent (query
     * builder inserts): no events tell which classes it touched, so the
     * whole cache goes once at the end.
     *
     * @param  Closure():T  $callback
     * @return T
     */
    public function bulk(Closure $callback, bool $everything = false): mixed
    {
        $this->bulk++;
        $this->bulkEverything = $this->bulkEverything || $everything;

        try {
            return $callback();
        } finally {
            if (--$this->bulk === 0) {
                $touched = $this->bulkTouched;
                $all = $this->bulkEverything;
                $this->bulkTouched = [];
                $this->bulkEverything = false;

                if ($all) {
                    $this->flushAll();
                } else {
                    foreach ($touched as $class) {
                        $this->flushClass($class);
                    }
                }
            }
        }
    }

    /**
     * Add pages for atrium:cache:warm to open in a language: the theme adds
     * its home page and the first page of every list.
     *
     * @param  Closure(string):iterable<string>  $paths  language => addresses or paths
     */
    public function warmUsing(Closure $paths): void
    {
        $this->warmers[] = $paths;
    }

    /**
     * The paths atrium:cache:warm opens in a language, the site's home first.
     *
     * @return array<int,string>
     */
    public function warmPaths(string $language): array
    {
        $paths = ['/'];

        foreach ($this->warmers as $warmer) {
            foreach ($warmer($language) as $url) {
                $path = (string) parse_url((string) $url, PHP_URL_PATH);
                $query = parse_url((string) $url, PHP_URL_QUERY);
                $paths[] = '/'.ltrim($path, '/').(is_string($query) && $query !== '' ? '?'.$query : '');
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Add a module's data to every card: called once per batch of records
     * being built, in the card's language; returns id => data.
     *
     * @param  Closure(Collection<int,Model>,string):array<int|string,mixed>  $extender
     */
    public function extend(string $name, Closure $extender): void
    {
        $this->extenders[$name] = $extender;
        $this->builder = null;
    }

    /**
     * How the cards get thumbnail addresses for their pictures:
     * fn (string $path, string $url): array<string,string> size => url.
     */
    public function thumbnailsUsing(?Closure $resolver): void
    {
        $this->thumbnails = $resolver;
        $this->builder = null;
    }

    public function builder(): CardBuilder
    {
        return $this->builder ??= new CardBuilder($this->extenders, $this->thumbnails);
    }

    /**
     * The time list queries compare against, in steps (a minute by
     * default): `now()` would make every query, and so every key, new.
     */
    public function now(): Carbon
    {
        $step = max(1, (int) config('atrium.cache.time_step', 60));

        return Carbon::createFromTimestamp(intdiv(Carbon::now()->getTimestamp(), $step) * $step, date_default_timezone_get());
    }

    /**
     * @return array{hits:int,misses:int}
     */
    public function requestStats(): array
    {
        return $this->stats;
    }

    /**
     * This request's counts, or, once the request has been closed by
     * reset(), the counts it closed with: for whoever reads them at the very
     * end (the monitoring module, through the atrium.cache.stats filter).
     *
     * @return array{hits:int,misses:int}
     */
    public function finishedStats(): array
    {
        return $this->stats['hits'] + $this->stats['misses'] > 0 ? $this->stats : $this->lastStats;
    }

    /**
     * The version names read through during this request: a cached page
     * stays valid while all of them stay the same.
     *
     * @return array<int,string>
     */
    public function readNames(): array
    {
        return array_keys($this->read);
    }

    /**
     * Keep the page cache off this request's page: a one-time token, a
     * captcha question, anything made for this visitor alone.
     */
    public function dontKeepPage(): void
    {
        $this->read[self::NO_PAGE] = self::NO_PAGE;
    }

    public function pageKeepable(): bool
    {
        return ! isset($this->read[self::NO_PAGE]);
    }

    /**
     * @param  array<int,string>  $names
     */
    public function markRead(array $names): void
    {
        foreach ($names as $name) {
            $this->read[$name] = $name;
        }
    }

    /**
     * Start afresh for the next request or job.
     */
    public function reset(): void
    {
        if ($this->stats['hits'] + $this->stats['misses'] > 0) {
            $this->lastStats = $this->stats;
        }

        $this->read = [];
        $this->stats = ['hits' => 0, 'misses' => 0];
        $this->versions->reset();
        $this->store->reset();
    }

    /**
     * Add this request's hits and misses to the totals the admin shows.
     */
    public function persistStats(): void
    {
        if (! config('atrium.cache.stats', true)) {
            return;
        }

        try {
            $repository = $this->store->repository();

            foreach ($this->stats as $name => $count) {
                if ($count > 0 && $repository->increment('atrium:stats:'.$name, $count) === false) {
                    $repository->forever('atrium:stats:'.$name, $count);
                }
            }
        } catch (Throwable) {
            // Statistics are not worth an error page.
        }
    }

    /**
     * @return array{hits:int,misses:int}
     */
    public function totals(): array
    {
        $repository = $this->store->repository();

        return [
            'hits' => (int) $repository->get('atrium:stats:hits', 0),
            'misses' => (int) $repository->get('atrium:stats:misses', 0),
        ];
    }

    public function resetTotals(): void
    {
        $this->store->repository()->forget('atrium:stats:hits');
        $this->store->repository()->forget('atrium:stats:misses');
    }

    public static function group(string $class): string
    {
        return str_replace('\\', '.', $class);
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<int,int|string>  $ids
     */
    private function forgetCards(string $class, array $ids): void
    {
        $sites = $this->knownSites();
        $locales = $this->knownLocales();
        $keys = [];

        foreach ($sites as $site) {
            foreach ($locales as $locale) {
                $prefix = $this->cardPrefix($class, $site, $locale, false);

                foreach ($ids as $id) {
                    $keys[] = $prefix.$id;
                }
            }
        }

        $this->store->forget(self::group($class), $keys);

        // Inside a transaction a request may build the card again from the
        // old row before the commit: drop it once more after the commit.
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->store->forget(self::group($class), $keys));
        }
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function cardPrefix(string $class, int $site, string $locale, bool $markRead = true): string
    {
        $names = ['all', 'site:'.$site, 'card:'.$class];

        foreach ($this->builder()->dependenciesOf($class) as $dependency) {
            $names[] = 'list:'.$dependency;
        }

        if ($markRead) {
            $this->markRead($names);
        }

        return 'card:'.$this->versions->stamp($names).':'.$site.':'.$locale.':';
    }

    /**
     * @return array<int,int>
     */
    private function knownSites(): array
    {
        try {
            $ids = app(Sites::class)->all()->pluck('id')->filter()->all();
        } catch (Throwable) {
            $ids = [];
        }

        return array_unique([0, ...$ids]);
    }

    /**
     * @return array<int,string>
     */
    private function knownLocales(): array
    {
        try {
            $codes = Language::query()->pluck('code')->all();
        } catch (Throwable) {
            $codes = [];
        }

        return array_unique([app()->getLocale(), ...$codes]);
    }

    private function site(): int
    {
        $sites = app(Sites::class);

        return $sites->isResolved() ? (int) $sites->current()->getKey() : 0;
    }

    private function ttl(string $kind): int
    {
        return max(1, (int) config('atrium.cache.ttl.'.$kind, $kind === 'cards' ? 86400 : 3600));
    }

    /**
     * @param  array<int,int|string>  $ids
     * @param  array<int|string,array<string,mixed>>  $data
     * @return array<int,Card>
     */
    private function ordered(array $ids, array $data): array
    {
        $cards = [];

        foreach ($ids as $id) {
            if (isset($data[$id])) {
                $cards[] = new Card($data[$id]);
            }
        }

        return $cards;
    }

    private static function normalizeId(mixed $id): int|string
    {
        return is_int($id) || (is_string($id) && ctype_digit($id) && strlen($id) < 19) ? (int) $id : (string) $id;
    }

    private static function binding(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s.u'),
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            default => $value,
        };
    }
}
