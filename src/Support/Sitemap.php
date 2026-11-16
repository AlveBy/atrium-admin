<?php

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Contracts\Sitemapable;
use Atrium\Admin\Resources\ResourceRegistry;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use XMLWriter;

/**
 * sitemap.xml of the current site: the paths from config, then every public
 * record of the modules that implement Sitemapable, one <url> per language
 * with the other languages as xhtml:link alternates.
 *
 * Search engines take at most 50 000 addresses per file; past that the
 * sitemap becomes an index of numbered parts. Built once and kept in the
 * cache until a record, a site or a language changes.
 */
final class Sitemap
{
    public const PER_FILE = 50000;

    private const VERSION_KEY = 'atrium.sitemap.version';

    public function __construct(
        private readonly Sites $sites,
        private readonly ContentLanguages $languages,
        private readonly ResourceRegistry $resources,
    ) {}

    public function parts(): int
    {
        return (int) ceil(count($this->entries()) / $this->perFile());
    }

    /**
     * The whole sitemap when it fits in one file, the index of its parts otherwise.
     */
    public function index(): string
    {
        if ($this->parts() <= 1) {
            return $this->part(1);
        }

        $xml = $this->writer();
        $xml->startElement('sitemapindex');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        for ($part = 1; $part <= $this->parts(); $part++) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', url("sitemap-{$part}.xml"));
            $xml->endElement();
        }

        $xml->endElement();

        return $xml->outputMemory();
    }

    public function part(int $part): string
    {
        $entries = array_slice($this->entries(), ($part - 1) * $this->perFile(), $this->perFile());

        $xml = $this->writer();
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

        foreach ($entries as $entry) {
            $xml->startElement('url');
            $xml->writeElement('loc', $entry['loc']);

            if ($entry['lastmod'] !== null) {
                $xml->writeElement('lastmod', $entry['lastmod']);
            }

            foreach ($entry['alternates'] as $hreflang => $href) {
                $xml->startElement('xhtml:link');
                $xml->writeAttribute('rel', 'alternate');
                $xml->writeAttribute('hreflang', $hreflang);
                $xml->writeAttribute('href', $href);
                $xml->endElement();
            }

            $xml->endElement();
        }

        $xml->endElement();

        return $xml->outputMemory();
    }

    /**
     * Every address of the current site, as it is served on this host.
     *
     * @return array<int,array{loc:string,lastmod:string|null,alternates:array<string,string>}>
     */
    public function entries(): array
    {
        $key = 'atrium.sitemap.'.$this->sites->current()->getKey().'.'.md5(url('/')).'.'.Cache::get(self::VERSION_KEY, '0');

        return Cache::remember($key, now()->addDay(), fn (): array => $this->build());
    }

    /**
     * Called whenever a record, a site or a language is saved or deleted.
     * Old copies are left to expire: the version is part of the key.
     */
    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::ulid());
    }

    /**
     * @return array<int,array{loc:string,lastmod:string|null,alternates:array<string,string>}>
     */
    private function build(): array
    {
        $entries = [];

        foreach ((array) config('atrium.sitemap.paths', ['/']) as $path) {
            $entries[] = ['loc' => url((string) $path), 'lastmod' => null, 'alternates' => []];
        }

        foreach ($this->resources->all() as $model) {
            if (! is_subclass_of($model, Sitemapable::class)) {
                continue;
            }

            // The query is the module's own: a record of some other model in
            // it has no sitemapUrl() to ask.
            foreach ($model::sitemapQuery()->lazy(500) as $record) {
                if ($record instanceof Sitemapable) {
                    array_push($entries, ...$this->entriesOf($record));
                }
            }
        }

        return $entries;
    }

    /**
     * One entry per language the record has a page in; each lists all of them.
     *
     * @return array<int,array{loc:string,lastmod:string|null,alternates:array<string,string>}>
     */
    private function entriesOf(Model&Sitemapable $record): array
    {
        $urls = [];

        foreach ($this->languages->all() as $language) {
            $url = $record->sitemapUrl($language->code);

            if ($url !== null && $url !== '') {
                $urls[$language->hreflang()] = $url;
            }
        }

        $alternates = [];

        if (count($urls) > 1) {
            $alternates = $urls;
            $default = $this->languages->all()->first();

            if ($default !== null && isset($urls[$default->hreflang()])) {
                $alternates['x-default'] = $urls[$default->hreflang()];
            }
        }

        $updated = $record->usesTimestamps() ? $record->getAttribute($record->getUpdatedAtColumn()) : null;
        $lastmod = $updated instanceof DateTimeInterface ? $updated->format(DateTimeInterface::ATOM) : null;

        return array_map(
            static fn (string $url): array => ['loc' => $url, 'lastmod' => $lastmod, 'alternates' => $alternates],
            array_values($urls),
        );
    }

    private function perFile(): int
    {
        return max(1, (int) config('atrium.sitemap.per_file', self::PER_FILE));
    }

    private function writer(): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        return $xml;
    }
}
