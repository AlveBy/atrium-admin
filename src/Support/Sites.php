<?php

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * The sites of the panel and the one the current request is about.
 *
 * On the public side the site comes from the domain; in the admin it is the
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
     * The active site that claims the host; the default site otherwise, so
     * localhost and a fresh server's IP open something.
     */
    public function forHost(string $host): Site
    {
        return $this->all()->first(fn (Site $site): bool => $site->active && $site->hasDomain($host))
            ?? $this->default();
    }

    public function current(): Site
    {
        return ($this->resolved ? $this->find($this->currentId) : null) ?? $this->default();
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
