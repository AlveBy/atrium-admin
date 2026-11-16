<?php

declare(strict_types=1);

use Atrium\Admin\Dashboard\Gadgets\PerformanceGadget;
use Atrium\Admin\Dashboard\Gadgets\SiteSpeedGadget;
use Atrium\Admin\Models\AdminUser;

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Panel Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for admin panel routes.
    |
    */
    'prefix' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | Eloquent models managed by the panel. Each must implement
    | Atrium\Admin\Contracts\Administrable. Listing a model here is all it
    | takes: routes, list, form, menu entry and API appear by themselves.
    |
    */
    'resources' => [
        // App\Models\Article::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Installed module packages
    |--------------------------------------------------------------------------
    |
    | Composer packages of type "atrium-module" that are installed, in menu
    | order. `php artisan atrium:module:install vendor/name` adds one here
    | and runs its migrations, `atrium:module:remove` takes it out. A module
    | that is required but not listed stays out of the panel.
    |
    */
    'modules' => [
        // 'alveby/atrium-brands',
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo
    |--------------------------------------------------------------------------
    |
    | A small shop to look around: a catalog shaped like a migrated Bitrix
    | iblock, brands, news in three languages on two sites, stores, between
    | them every field type. `php artisan atrium:install --demo` turns it on
    | and fills it in; set to false and the demo leaves the menu and the site
    | (its tables stay).
    |
    */
    'demo' => (bool) env('ATRIUM_DEMO', false),

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to apply to admin panel routes.
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Settings for admin authentication.
    |
    */
    'auth' => [
        'guard' => 'admin',
        'model' => AdminUser::class,
        'login_route' => 'admin.login',
        'redirect_after_login' => 'admin.dashboard',
        'redirect_after_logout' => 'admin.login',

        /*
        | Sign-in confirmation with a code sent by email.
        |
        | A password lives until someone changes it; the code goes to the
        | mailbox and is valid for minutes. Turn it off only where there is no
        | mail — in browser tests, for example.
        |
        | ttl — how many minutes the code is valid, resend_after — after how
        | many seconds a new email may be requested.
        */
        'two_factor' => [
            'enabled' => (bool) env('ATRIUM_2FA_ENABLED', true),
            'ttl' => (int) env('ATRIUM_2FA_TTL', 10),
            'resend_after' => (int) env('ATRIUM_2FA_RESEND_AFTER', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Interface languages
    |--------------------------------------------------------------------------
    |
    | Languages of the panel itself (not of the site content). Each admin picks
    | one in the header; guests get the browser's language if it is listed,
    | otherwise `locale`. Remove entries to hide them from the switcher.
    | Translations live in lang/{code}/*.php of this package and can be
    | overridden with `php artisan vendor:publish --tag=atrium-lang`.
    |
    */
    'locale' => env('ATRIUM_LOCALE', 'en'),

    'locales' => [
        'en' => 'English',
        'ru' => 'Русский',
        'uk' => 'Українська',
        'be' => 'Беларуская',
        'kk' => 'Қазақша',
        'uz' => 'Oʻzbekcha',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
        'pt_BR' => 'Português (Brasil)',
        'it' => 'Italiano',
        'pl' => 'Polski',
        'tr' => 'Türkçe',
        'zh_CN' => '简体中文',
        'ja' => '日本語',
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Admin panel branding settings.
    |
    */
    'branding' => [
        'name' => env('ATRIUM_NAME', 'Atrium'),
        'logo' => env('ATRIUM_LOGO'),
        'favicon' => env('ATRIUM_FAVICON'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    |
    | Fixed entries of the sidebar. Modules from `resources` that are not
    | listed here are added automatically right after the first entry.
    | A label may be a translation key (`atrium::menu.dashboard`, or one of
    | your own in lang/) or plain text.
    |
    */
    'menu' => [
        [
            'label' => 'atrium::menu.dashboard',
            'route' => 'admin.dashboard',
            'icon' => 'home',
        ],
        [
            'label' => 'atrium::menu.administrators',
            'route' => 'admin.users.index',
            'icon' => 'users',
        ],
        [
            'label' => 'atrium::menu.roles',
            'route' => 'admin.roles.index',
            'icon' => 'shield',
        ],
        [
            'label' => 'atrium::menu.sites',
            'route' => 'admin.sites.index',
            'icon' => 'home',
        ],
        [
            'label' => 'atrium::menu.languages',
            'route' => 'admin.languages.index',
            'icon' => 'globe',
        ],
        [
            'label' => 'atrium::menu.api_tokens',
            'route' => 'admin.api-tokens.index',
            'icon' => 'settings',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    |
    | Settings for file uploads in admin panel.
    |
    */
    'uploads' => [
        'disk' => 'public',
        'directory' => 'uploads',
        'max_size' => 5120, // KB
        'image_max_size' => 2048, // KB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],

        // Whether to put a lighter webp copy next to an uploaded image.
        // The web server serves it by the Accept header — see public/.htaccess.
        // Turn it off only where there is no such rule: the copies would take
        // disk space and never reach visitors.
        'webp' => env('ATRIUM_UPLOADS_WEBP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slug Settings
    |--------------------------------------------------------------------------
    |
    | Settings for URL slugs and caching.
    |
    */
    'slug' => [
        'cache_prefix' => 'slug',
        'cache_ttl' => 86400, // 24 hours
        'auto_redirect' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Settings
    |--------------------------------------------------------------------------
    |
    | Default SEO settings and templates.
    |
    */
    'seo' => [
        'title_suffix' => ' | '.env('APP_NAME', 'Laravel'),
        'default_description' => '',
        'templates' => [
            // 'App\Models\Product' => [
            //     'title' => '{name} - buy online',
            //     'description' => '{name}. {preview_text}',
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public addresses of the modules
    |--------------------------------------------------------------------------
    |
    | Which modules have pages on the site and where. The slug field shows
    | the address under the code, the API returns it as `public_url`, and the
    | theme (alveby/atrium-theme) draws the pages themselves.
    |
    |   prefix         the first part of the address: /news/…
    |   tree           the section model, for addresses with the section path
    |   parent         the column holding the section (the parent section for
    |                  the section model itself)
    |   parent_column  the section model's own parent column, parent_id
    |
    |   App\Models\News::class => ['prefix' => 'news'],
    |   App\Models\Product::class => ['prefix' => 'catalog', 'tree' => App\Models\ProductSection::class, 'parent' => 'section_id'],
    |   App\Models\ProductSection::class => ['prefix' => 'catalog', 'tree' => App\Models\ProductSection::class, 'parent' => 'parent_id'],
    |
    | gives /news/{code}, /catalog/{section}/{subsection}/{code} and
    | /catalog/{section}/{subsection}. A module missing here has no page.
    |
    */
    'public_urls' => [],

    /*
    |--------------------------------------------------------------------------
    | robots.txt, sitemap.xml and counters
    |--------------------------------------------------------------------------
    |
    | robots — the core answers /robots.txt of every site with the text set
    | in the admin (Sites → robots.txt). Off production it always says
    | "Disallow: /". Set to false to serve your own public/robots.txt; while
    | it is on, that file must not exist: the web server would return it
    | before Laravel is asked.
    |
    | sitemap — /sitemap.xml of every site: the `paths` below plus the public
    | records of modules that implement Atrium\Admin\Contracts\Sitemapable,
    | in all the site's languages. Past `per_file` addresses it becomes an
    | index of /sitemap-1.xml, /sitemap-2.xml, …
    |
    | counters.consent — Google Tag and Yandex Metrica start only after the
    | visitor agrees: the site's cookie banner calls window.atriumConsent().
    |
    */
    'robots' => (bool) env('ATRIUM_ROBOTS', true),

    'sitemap' => [
        'enabled' => (bool) env('ATRIUM_SITEMAP', true),
        'paths' => ['/'],
        'per_file' => 50000,
    ],

    'counters' => [
        'consent' => (bool) env('ATRIUM_COUNTERS_CONSENT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Map Settings
    |--------------------------------------------------------------------------
    |
    | API keys for map services.
    |
    */
    'maps' => [
        'yandex_api_key' => env('YANDEX_MAPS_API_KEY'),
        'google_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default pagination settings for admin lists.
    |
    */
    'pagination' => [
        'per_page' => 20,
        'per_page_options' => [10, 20, 50, 100],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | Dashboard gadgets (tiles). Each `gadgets` entry is either a class name
    | or an array with a `gadget` key plus instance options ('title',
    | 'width', 'model', 'count', 'enabled').
    |
    | Only gadgets that need no knowledge of the project's models are listed
    | here. The "recent items" gadget (RecentElementsGadget) is added in the
    | application config, where those models exist.
    |
    | `timings` — recording of public page generation time; both speed
    | gadgets live on it. Turning it off disables the middleware as well.
    |
    */
    'dashboard' => [
        'timings' => [
            'enabled' => env('ATRIUM_TIMINGS', true),
            'sample_rate' => (float) env('ATRIUM_TIMINGS_SAMPLE_RATE', 1.0),
            'keep_days' => 30,
        ],

        'gadgets' => [
            SiteSpeedGadget::class,
            PerformanceGadget::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    |
    | Admin modules over an API — for external programs and AI agents. Tokens
    | are issued in the "API tokens" section; the documentation is open at
    | `prefix` (Markdown) and `prefix/openapi.json`.
    |
    | Every module registered in the panel is exposed automatically.
    | `except` — module keys that must never be in the API: administrators
    | are not created with a token.
    |
    */
    'api' => [
        'prefix' => 'api/v1',
        'except' => ['users'],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP
    |--------------------------------------------------------------------------
    |
    | The API's modules for AI assistants (Claude, ChatGPT, Cursor…) at
    | `path`, with the same tokens: an assistant may do exactly what its token
    | allows. Every tool call goes through the API, so the rules are the same.
    |
    */
    'mcp' => [
        'enabled' => (bool) env('ATRIUM_MCP_ENABLED', true),
        'path' => 'mcp',
    ],
];
