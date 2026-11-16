<?php

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Models\Language;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * The languages the site's content is written in, as set in the admin.
 *
 * Read once per request. Before the migrations have run there is no table;
 * the app's own locale then stands in, so nothing that only reads content
 * breaks on a fresh install.
 */
final class ContentLanguages
{
    /** @var Collection<int,Language>|null */
    private ?Collection $active = null;

    /**
     * Active languages, the default one first, then by sort.
     *
     * @return Collection<int,Language>
     */
    public function all(): Collection
    {
        return $this->active ??= $this->load();
    }

    /**
     * @return array<int,string>
     */
    public function codes(): array
    {
        return $this->all()->pluck('code')->all();
    }

    public function default(): string
    {
        return (string) $this->all()->first()?->code;
    }

    public function has(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    public function find(string $code): ?Language
    {
        return $this->all()->firstWhere('code', $code);
    }

    /**
     * Whether editors need a language switch at all.
     */
    public function isMultilingual(): bool
    {
        return $this->all()->count() > 1;
    }

    /**
     * The URL of a route defined inside Route::localized() in a given
     * language: the default language has no prefix, the others do (/de/…).
     *
     * @param  array<string,mixed>  $parameters
     */
    public function route(string $name, array $parameters = [], ?string $code = null, bool $absolute = true): string
    {
        $code ??= app()->getLocale();

        if ($code === $this->default()) {
            return route($name, $parameters, $absolute);
        }

        return route('localized.'.$name, ['locale' => $code, ...$parameters], $absolute);
    }

    public function flush(): void
    {
        $this->active = null;
    }

    /**
     * @return Collection<int,Language>
     */
    private function load(): Collection
    {
        try {
            $languages = Language::query()
                ->where('active', true)
                ->orderByDesc('is_default')
                ->orderBy('sort')
                ->orderBy('id')
                ->get();
        } catch (QueryException) {
            $languages = new Collection;
        }

        if ($languages->isEmpty()) {
            $code = str_replace('_', '-', strtolower((string) config('app.locale', 'en')));
            $languages = new Collection([new Language(['code' => $code, 'name' => strtoupper($code), 'active' => true, 'is_default' => true])]);
        }

        return $languages;
    }
}
