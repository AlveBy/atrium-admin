<?php

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Atrium\Admin\Columns\BooleanColumn;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\RepeatableField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sitemap;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tabs\Tab;
use Atrium\Admin\Traits\HasAdminTabs;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A site served by the panel, picked by the domain a visitor came to and,
 * for several sites on one domain, by the folder it sits in
 * (example.com/site2/) — what b_lang with its DIR is in Bitrix. Records of
 * modules that use BelongsToSite belong to one site; other modules are
 * shared by all of them.
 *
 * @property int|null $id
 * @property string $code
 * @property string $name
 * @property array<int,string>|null $domains
 * @property string|null $folder
 * @property array<int,string>|null $languages
 * @property string|null $default_language
 * @property bool $active
 * @property bool $is_default
 * @property int $sort
 * @property string|null $google_tag_id
 * @property string|null $yandex_metrika_id
 * @property bool $yandex_webvisor
 * @property bool $counters_skip_admins
 * @property string|null $google_verification
 * @property string|null $yandex_verification
 * @property string|null $bing_verification
 * @property string|null $head_code
 * @property string|null $body_start_code
 * @property string|null $body_end_code
 * @property string|null $robots_txt
 */
class Site extends Model implements Administrable
{
    use HasAdminTabs, InteractsWithAdmin;

    public const CODE_PATTERN = '[a-z0-9][a-z0-9_-]{0,31}';

    private const DOMAIN_PATTERN = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/';

    /** One or more path segments: "site2", "en", "shop/b2b". */
    private const FOLDER_PATTERN = '#^(?=.{1,255}$)[A-Za-z0-9][A-Za-z0-9_-]*(/[A-Za-z0-9][A-Za-z0-9_-]*)*$#';

    protected $table = 'admin_sites';

    protected $fillable = [
        'code', 'name', 'domains', 'folder', 'languages', 'default_language', 'active', 'is_default', 'sort',
        'google_tag_id', 'yandex_metrika_id', 'yandex_webvisor', 'counters_skip_admins',
        'google_verification', 'yandex_verification', 'bing_verification',
        'head_code', 'body_start_code', 'body_end_code', 'robots_txt',
    ];

    /** Google Analytics 4, Ads, Floodlight, a Google tag or a Tag Manager container. */
    private const GOOGLE_TAG_PATTERN = '/^(G|GT|AW|DC|GTM)-[A-Z0-9]{4,}$/i';

    private const CODE_COLUMNS = ['head_code', 'body_start_code', 'body_end_code', 'robots_txt'];

    private const VERIFICATION_COLUMNS = ['google_verification', 'yandex_verification', 'bing_verification'];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'languages' => 'array',
            'active' => 'boolean',
            'is_default' => 'boolean',
            'sort' => 'integer',
            'yandex_webvisor' => 'boolean',
            'counters_skip_admins' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Site $site): void {
            $others = static::query()->where('is_default', true)->whereKeyNot($site->getKey());

            if (! $site->is_default && ! $others->exists()) {
                $site->is_default = true;
            }

            if ($site->is_default) {
                $site->active = true;
            }

            if ($site->exists && $site->isDirty('code')) {
                $site->code = (string) $site->getOriginal('code');
            }

            $site->domains = $site->normalizedDomains();
            $site->folder = self::normalizeFolder($site->folder);
            $site->guardFolder();
            $site->guardDomains();

            $languages = array_values(array_unique(array_filter((array) $site->languages, static fn (string $code): bool => $code !== '')));
            $site->languages = $languages === [] ? null : $languages;

            if ($site->default_language === '') {
                $site->default_language = null;
            }

            // A site's own default language must be one of its languages.
            if ($languages !== [] && ! in_array($site->default_language, $languages, true)) {
                $site->default_language = $languages[0];
            }

            $site->normalizeCodes();
        });

        static::saved(function (Site $site): void {
            if ($site->is_default) {
                static::query()->where('is_default', true)->whereKeyNot($site->getKey())->update(['is_default' => false]);
            }

            app(Sites::class)->flush();
            app(ContentLanguages::class)->flush();
            app(Sitemap::class)->flush();
        });

        static::deleted(static function (): void {
            app(Sites::class)->flush();
            app(ContentLanguages::class)->flush();
            app(Sitemap::class)->flush();
        });
    }

    public static function getAdminLabel(): string
    {
        return __('atrium::menu.sites');
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'sites';
    }

    public static function getAdminCreateLabel(): string
    {
        return __('atrium::sites.create');
    }

    public static function getDefaultSortColumn(): string
    {
        return 'sort';
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAdminTabs(): array
    {
        return [
            Tab::make('params')->label(__('atrium::sites.tabs.site'))->order(0),
            Tab::make('codes')->label(__('atrium::sites.tabs.codes'))->order(10),
            Tab::make('robots')->label('robots.txt')->order(20),
        ];
    }

    public static function getAdministrationFields(): array
    {
        $languages = Language::query()->orderBy('sort')->orderBy('id')->pluck('name', 'code')->all();

        return [
            ...self::siteFields($languages),
            ...self::codeFields(),
            ...(config('atrium.robots', true) ? [self::robotsField()] : []),
        ];
    }

    /**
     * @param  array<string,string>  $languages
     * @return array<int,Field>
     */
    private static function siteFields(array $languages): array
    {
        return [
            TextField::make('name')
                ->label(__('atrium::sites.fields.name'))
                ->required()
                ->maxLength(255),

            TextField::make('code')
                ->label(__('atrium::sites.fields.code'))
                ->hint(__('atrium::sites.hints.code'))
                ->required()
                ->rules(['string', 'max:32', 'regex:/^'.self::CODE_PATTERN.'$/'])
                ->unique('admin_sites', 'code')
                ->onlyOnCreate(),

            TextField::make('code')
                ->label(__('atrium::sites.fields.code'))
                ->readonly()
                ->onlyOnEdit(),

            RepeatableField::make('domains')
                ->label(__('atrium::sites.fields.domains'))
                ->hint(__('atrium::sites.hints.domains'))
                ->itemType('text')
                ->addLabel(__('atrium::sites.add_domain')),

            TextField::make('folder')
                ->label(__('atrium::sites.fields.folder'))
                ->hint(__('atrium::sites.hints.folder'))
                ->placeholder('site2')
                ->maxLength(255),

            MultiSelectField::make('languages')
                ->label(__('atrium::sites.fields.languages'))
                ->hint(__('atrium::sites.hints.languages'))
                ->options($languages),

            SelectField::make('default_language')
                ->label(__('atrium::sites.fields.default_language'))
                ->hint(__('atrium::sites.hints.default_language'))
                ->options($languages),

            CheckboxField::make('active')
                ->label(__('atrium::sites.fields.active'))
                ->default(true),

            CheckboxField::make('is_default')
                ->label(__('atrium::sites.fields.is_default'))
                ->hint(__('atrium::sites.hints.is_default'))
                ->default(false),

            NumberField::make('sort')
                ->label(__('atrium::sites.fields.sort'))
                ->default(500),
        ];
    }

    /**
     * Counters and verification: the editor gives an ID, the core writes the
     * snippet (see the head, body-start and body-end components).
     *
     * @return array<int,Field>
     */
    private static function codeFields(): array
    {
        $fields = [
            TextField::make('google_tag_id')
                ->label(__('atrium::sites.fields.google_tag_id'))
                ->hint(__('atrium::sites.hints.google_tag_id'))
                ->placeholder('G-XXXXXXXXXX')
                ->rules(['string', 'max:32', 'regex:'.self::GOOGLE_TAG_PATTERN]),

            TextField::make('yandex_metrika_id')
                ->label(__('atrium::sites.fields.yandex_metrika_id'))
                ->hint(__('atrium::sites.hints.yandex_metrika_id'))
                ->placeholder('12345678')
                ->rules(['string', 'max:20', 'regex:/^\\s*\\d+\\s*$/']),

            CheckboxField::make('yandex_webvisor')
                ->label(__('atrium::sites.fields.yandex_webvisor'))
                ->default(false),

            CheckboxField::make('counters_skip_admins')
                ->label(__('atrium::sites.fields.counters_skip_admins'))
                ->hint(__('atrium::sites.hints.counters_skip_admins'))
                ->default(true),

            TextField::make('google_verification')
                ->label(__('atrium::sites.fields.google_verification'))
                ->hint(__('atrium::sites.hints.verification', ['name' => 'google-site-verification']))
                ->rules(['string', 'max:1000']),

            TextField::make('yandex_verification')
                ->label(__('atrium::sites.fields.yandex_verification'))
                ->hint(__('atrium::sites.hints.verification', ['name' => 'yandex-verification']))
                ->rules(['string', 'max:1000']),

            TextField::make('bing_verification')
                ->label(__('atrium::sites.fields.bing_verification'))
                ->hint(__('atrium::sites.hints.verification', ['name' => 'msvalidate.01']))
                ->rules(['string', 'max:1000']),

            TextareaField::make('head_code')
                ->label(__('atrium::sites.fields.head_code'))
                ->hint(__('atrium::sites.hints.snippet'))
                ->rows(6),

            TextareaField::make('body_start_code')
                ->label(__('atrium::sites.fields.body_start_code'))
                ->hint(__('atrium::sites.hints.snippet'))
                ->rows(6),

            TextareaField::make('body_end_code')
                ->label(__('atrium::sites.fields.body_end_code'))
                ->hint(__('atrium::sites.hints.snippet'))
                ->rows(6),
        ];

        foreach ($fields as $field) {
            $field->tab('codes');
        }

        return $fields;
    }

    /**
     * The hint also says when the text will not be served: off production,
     * or when public/robots.txt is there and the web server answers first.
     */
    private static function robotsField(): TextareaField
    {
        $hint = [__('atrium::sites.hints.robots_txt')];

        if (! app()->isProduction()) {
            $hint[] = __('atrium::sites.hints.robots_not_production', ['env' => app()->environment()]);
        }

        if (is_file(public_path('robots.txt'))) {
            $hint[] = __('atrium::sites.hints.robots_file_exists');
        }

        return TextareaField::make('robots_txt')
            ->label('robots.txt')
            ->hint(implode(' ', $hint))
            ->placeholder("User-agent: *\nDisallow:")
            ->rows(12)
            ->tab('robots');
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('name')->label(__('atrium::sites.fields.name'))->sortable()->searchable(),
            TextColumn::make('code')->label(__('atrium::sites.fields.code'))->sortable()->searchable(),
            TextColumn::make('folder')->label(__('atrium::sites.fields.folder'))->sortable(),
            BooleanColumn::make('active')->label(__('atrium::sites.fields.active')),
            BooleanColumn::make('is_default')->label(__('atrium::sites.fields.is_default')),
            TextColumn::make('sort')->label(__('atrium::sites.fields.sort'))->sortable(),
        ];
    }

    /**
     * The default site answers every domain nobody else claims.
     */
    public function canDelete(): bool
    {
        return ! $this->is_default;
    }

    /**
     * The address of a page of the site, its front page by default: the
     * domain the request came to if the site claims it, else its first
     * domain on the port the panel itself is opened on (localhost:8000 in
     * development); then the site's folder.
     */
    public function url(string $path = ''): string
    {
        $request = request();
        $domain = $this->domains[0] ?? null;

        if ($domain === null || $this->hasDomain($request->getHost())) {
            $root = Sites::rootUrl($request);
        } else {
            $port = $request->getPort();
            $standard = ($request->isSecure() && $port === 443) || (! $request->isSecure() && $port === 80);
            $root = $request->getScheme().'://'.$domain.($standard || $port === null ? '' : ':'.$port);
        }

        $path = trim(implode('/', [(string) $this->folder, trim($path, '/')]), '/');

        return $path === '' ? $root : $root.'/'.$path;
    }

    /**
     * The robots.txt this site answers with on the root of a domain. Off
     * production every robot is turned away whatever the admin wrote: a
     * staging copy must not get into search results even if nobody thought
     * about it.
     *
     * robots.txt is one per domain, so it lists the sitemaps of the sites
     * in folders of that domain as well.
     *
     * @param  iterable<Site>  $folderSites
     */
    public function robotsTxt(iterable $folderSites = []): string
    {
        if (! app()->isProduction()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $text = trim(str_replace("\r\n", "\n", (string) $this->robots_txt));

        if ($text === '') {
            $text = "User-agent: *\nDisallow:";
        }

        if (config('atrium.sitemap.enabled', true) && ! preg_match('/^\s*sitemap\s*:/mi', $text)) {
            $text .= "\n\nSitemap: ".url('sitemap.xml');

            foreach ($folderSites as $site) {
                $text .= "\nSitemap: ".url($site->folder.'/sitemap.xml');
            }
        }

        return $text."\n";
    }

    public function hasDomain(string $host): bool
    {
        return in_array(strtolower(rtrim($host, '.')), (array) $this->domains, true);
    }

    /**
     * Whether a path (/site2/de/news) is inside the site's folder. "site2"
     * holds /site2 and /site2/…, never /site22.
     */
    public function holdsPath(string $path): bool
    {
        if ($this->folder === null || $this->folder === '') {
            return false;
        }

        return str_starts_with('/'.trim($path, '/').'/', '/'.$this->folder.'/');
    }

    /**
     * "/site2/", "https://example.com/site2" → "site2"; nothing → null.
     */
    public static function normalizeFolder(mixed $folder): ?string
    {
        $folder = trim((string) $folder);
        $folder = (string) preg_replace('#^[a-z][a-z0-9+.-]*://[^/]*#i', '', $folder);
        $folder = (string) preg_replace('#[?\#].*$#', '', $folder);
        $folder = trim((string) preg_replace('#/+#', '/', $folder), '/');

        return $folder === '' ? null : $folder;
    }

    /**
     * "https://Example.com:8080/path" → "example.com": editors paste whatever
     * the browser shows.
     *
     * @return array<int,string>
     */
    private function normalizedDomains(): array
    {
        $domains = [];

        foreach ((array) $this->domains as $domain) {
            $domain = strtolower(trim((string) $domain));
            $domain = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $domain);
            $domain = (string) preg_replace('#[/?\#].*$#', '', $domain);
            $domain = rtrim((string) preg_replace('#:\d+$#', '', $domain), '.');

            if ($domain !== '' && ! in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    /**
     * Editors paste what the search console shows them: the whole
     * <meta name="…" content="token"> tag, or just the token. Keep the token.
     */
    private function normalizeCodes(): void
    {
        $this->google_tag_id = strtoupper(trim((string) $this->google_tag_id)) ?: null;
        $this->yandex_metrika_id = trim((string) $this->yandex_metrika_id) ?: null;

        foreach (self::VERIFICATION_COLUMNS as $column) {
            $value = trim((string) $this->getAttribute($column));

            if (preg_match('/content\s*=\s*["\']([^"\']*)["\']/i', $value, $match)) {
                $value = trim($match[1]);
            }

            $this->setAttribute($column, $value === '' ? null : $value);
        }

        foreach (self::CODE_COLUMNS as $column) {
            $value = trim((string) $this->getAttribute($column));
            $this->setAttribute($column, $value === '' ? null : $value);
        }
    }

    /**
     * A folder is a path, not one of the panel's own addresses; the default
     * site answers every address nobody else claims, so it sits at the root.
     */
    private function guardFolder(): void
    {
        if ($this->folder === null) {
            return;
        }

        if (! preg_match(self::FOLDER_PATTERN, $this->folder)) {
            throw ValidationException::withMessages(['folder' => __('atrium::sites.errors.folder_invalid', ['folder' => $this->folder])]);
        }

        $first = strtolower(explode('/', $this->folder)[0]);
        $reserved = array_filter([
            strtolower(explode('/', trim((string) config('atrium.prefix', 'admin'), '/'))[0]),
            strtolower(explode('/', trim((string) config('atrium.api.prefix', 'api/v1'), '/'))[0]),
            strtolower(explode('/', trim((string) config('atrium.mcp.path', 'mcp'), '/'))[0]),
        ]);

        if (in_array($first, $reserved, true)) {
            throw ValidationException::withMessages(['folder' => __('atrium::sites.errors.folder_reserved', ['folder' => $first])]);
        }

        if ($this->is_default) {
            throw ValidationException::withMessages(['folder' => __('atrium::sites.errors.folder_default')]);
        }
    }

    /**
     * A domain is a host name, and a domain with a folder belongs to one
     * site only — otherwise which site opens would depend on the order of
     * rows. Sites in different folders may share a domain; sites without
     * domains live on every domain, so their folders are unique too.
     */
    private function guardDomains(): void
    {
        foreach ((array) $this->domains as $domain) {
            if (! preg_match(self::DOMAIN_PATTERN, $domain)) {
                throw ValidationException::withMessages(['domains' => __('atrium::sites.errors.domain_invalid', ['domain' => $domain])]);
            }
        }

        $taken = static::query()->whereKeyNot($this->getKey())->get(['name', 'domains', 'folder']);

        foreach ($taken as $other) {
            if ($other->folder !== $this->folder) {
                continue;
            }

            $clash = array_intersect((array) $this->domains, (array) $other->domains);

            if ($clash !== [] && $this->folder === null) {
                throw ValidationException::withMessages(['domains' => __('atrium::sites.errors.domain_taken', ['domain' => reset($clash), 'site' => $other->name])]);
            }

            if ($this->folder !== null && ($clash !== [] || ((array) $this->domains === [] && (array) $other->domains === []))) {
                $address = ($clash === [] ? '' : reset($clash)).'/'.$this->folder.'/';

                throw ValidationException::withMessages(['folder' => __('atrium::sites.errors.folder_taken', ['address' => $address, 'site' => $other->name])]);
            }
        }
    }
}
