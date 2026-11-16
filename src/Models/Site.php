<?php

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Atrium\Admin\Columns\BooleanColumn;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\RepeatableField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A site served by the panel, picked by the domain a visitor came to — what
 * b_lang is in Bitrix. Records of modules that use BelongsToSite belong to
 * one site; other modules are shared by all of them.
 *
 * @property int|null $id
 * @property string $code
 * @property string $name
 * @property array<int,string>|null $domains
 * @property array<int,string>|null $languages
 * @property string|null $default_language
 * @property bool $active
 * @property bool $is_default
 * @property int $sort
 */
class Site extends Model implements Administrable
{
    use InteractsWithAdmin;

    public const CODE_PATTERN = '[a-z0-9][a-z0-9_-]{0,31}';

    private const DOMAIN_PATTERN = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/';

    protected $table = 'admin_sites';

    protected $fillable = ['code', 'name', 'domains', 'languages', 'default_language', 'active', 'is_default', 'sort'];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'languages' => 'array',
            'active' => 'boolean',
            'is_default' => 'boolean',
            'sort' => 'integer',
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
        });

        static::saved(function (Site $site): void {
            if ($site->is_default) {
                static::query()->where('is_default', true)->whereKeyNot($site->getKey())->update(['is_default' => false]);
            }

            app(Sites::class)->flush();
            app(ContentLanguages::class)->flush();
        });

        static::deleted(static function (): void {
            app(Sites::class)->flush();
            app(ContentLanguages::class)->flush();
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

    public static function getAdministrationFields(): array
    {
        $languages = Language::query()->orderBy('sort')->orderBy('id')->pluck('name', 'code')->all();

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

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('name')->label(__('atrium::sites.fields.name'))->sortable()->searchable(),
            TextColumn::make('code')->label(__('atrium::sites.fields.code'))->sortable()->searchable(),
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
     * The address of the site's front page: its first domain, on the port
     * the panel itself is opened on (localhost:8000 in development).
     */
    public function url(): string
    {
        $domain = $this->domains[0] ?? null;

        if ($domain === null) {
            return url('/');
        }

        $request = request();
        $port = $request->getPort();
        $standard = ($request->isSecure() && $port === 443) || (! $request->isSecure() && $port === 80);

        return $request->getScheme().'://'.$domain.($standard || $port === null ? '' : ':'.$port);
    }

    public function hasDomain(string $host): bool
    {
        return in_array(strtolower(rtrim($host, '.')), (array) $this->domains, true);
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
     * A domain is a host name and belongs to one site only — otherwise which
     * site opens would depend on the order of rows.
     */
    private function guardDomains(): void
    {
        foreach ((array) $this->domains as $domain) {
            if (! preg_match(self::DOMAIN_PATTERN, $domain)) {
                throw ValidationException::withMessages(['domains' => __('atrium::sites.errors.domain_invalid', ['domain' => $domain])]);
            }
        }

        $taken = static::query()->whereKeyNot($this->getKey())->get(['name', 'domains']);

        foreach ($taken as $other) {
            $clash = array_intersect((array) $this->domains, (array) $other->domains);

            if ($clash !== []) {
                throw ValidationException::withMessages(['domains' => __('atrium::sites.errors.domain_taken', ['domain' => reset($clash), 'site' => $other->name])]);
            }
        }
    }
}
