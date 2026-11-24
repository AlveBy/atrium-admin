<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\PageTiming;

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

    // Installed modules switched off on the Modules screen: their data and
    // settings stay, but they are out of the panel, the API and the site
    // until switched on again.
    'disabled_modules' => [
    ],

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
        | The second sign-in step: a code sent by email, an authenticator app,
        | a passkey or an SMS. It lives in alveby/atrium-2fa (see its
        | config/atrium-2fa.php); without that package the password is enough.
        |
        | `enabled` false switches the e-mailed code off for administrators —
        | in browser tests, for example, where there is no mail. ttl — how
        | many minutes the code is valid, resend_after — after how many
        | seconds a new email may be requested.
        */
        'two_factor' => [
            'enabled' => (bool) env('ATRIUM_2FA_ENABLED', true),
            'ttl' => (int) env('ATRIUM_2FA_TTL', 10),
            'resend_after' => (int) env('ATRIUM_2FA_RESEND_AFTER', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | For a public demo of the panel (docs/demo.md), never for a real site.
    | `php artisan atrium:demo:reset` puts the database and the uploaded
    | files back as they were: the demo content and one administrator,
    | `email` / `password`, who is not a superadmin, edits, imports and
    | exports the content (images by address are not downloaded), looks at
    | the sections in `read_only` (administrators, sites, languages,
    | the uptime checks) but changes none of them, has no API tokens, no
    | module settings and cannot change their own name, e-mail or password.
    | `reset` is the cron expression that runs the reset when the scheduler
    | does (every hour).
    |
    */
    'demo' => [
        'enabled' => (bool) env('ATRIUM_DEMO', false),
        'email' => env('ATRIUM_DEMO_EMAIL', 'demo@atrium.test'),
        'password' => env('ATRIUM_DEMO_PASSWORD', 'demo'),
        'reset' => env('ATRIUM_DEMO_RESET', '0 * * * *'),
        'read_only' => ['users', 'languages', 'sites', 'uptime_checks', 'monitoring_incidents'],
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
    | Developer credit
    |--------------------------------------------------------------------------
    |
    | A small "Made by AlveSoft Solutions" link with the AlveSoft logo at the
    | bottom of the sidebar and under the sign-in form. Atrium is MIT
    | licensed and nothing requires it: set false to hide it.
    |
    */
    'credit' => (bool) env('ATRIUM_CREDIT', true),

    /*
    |--------------------------------------------------------------------------
    | Admin colour theme
    |--------------------------------------------------------------------------
    |
    | The theme an administrator gets until they pick their own in the profile
    | menu, and the theme of the sign-in page: graphite, day, night, sand,
    | contrast, or system (graphite or night, as the operating system is set).
    |
    */
    'admin' => [
        'theme' => env('ATRIUM_ADMIN_THEME', 'graphite'),
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
        [
            'label' => 'atrium::menu.cache',
            'route' => 'admin.cache.index',
            'icon' => 'settings',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Superadmin-only Routes
    |--------------------------------------------------------------------------
    |
    | Route-name prefixes only superadmins may open, like the roles screen.
    | Modules add their own here (the backup module adds `admin.backups.`).
    |
    */
    'superadmin_routes' => [],

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    |
    | Settings for file uploads in admin panel.
    |
    */
    'uploads' => [
        // A bucket (s3) works too: set its AWS_URL to the CDN. See docs/cdn.md.
        'disk' => env('ATRIUM_UPLOADS_DISK', 'public'),
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
    | CDN
    |--------------------------------------------------------------------------
    |
    | Off until `url` is set. Then every image and file address the admin and
    | the theme build points at the CDN (a pull zone whose origin is this
    | site), with ?v= of the file's time so a changed file gets a new address.
    | See docs/cdn.md.
    |
    */
    'cdn' => [
        // https://cdn.example.com — no trailing slash. Empty: off.
        'url' => env('ATRIUM_CDN_URL'),

        // Root-relative paths served through the CDN, in addresses and inside
        // the HTML of texts.
        'paths' => ['/storage/', '/upload/'],

        // Built JS and CSS (Vite) from the CDN too. Needs CORS on the CDN:
        // module scripts and fonts are loaded cross-origin.
        'assets' => env('ATRIUM_CDN_ASSETS', false),

        // ?v= with the file's modification time on local files.
        'version' => env('ATRIUM_CDN_VERSION', true),

        // Resizing by the CDN instead of PHP: null, cloudflare, bunny,
        // imgproxy or template.
        'images' => [
            'driver' => env('ATRIUM_CDN_IMAGES'),
            // imgproxy's own address; for the others the CDN's.
            'url' => env('ATRIUM_CDN_IMAGES_URL'),
            // auto (the CDN picks WebP or AVIF by the browser), webp or avif.
            'format' => env('ATRIUM_CDN_IMAGES_FORMAT', 'auto'),
            'quality' => (int) env('ATRIUM_CDN_IMAGES_QUALITY', 82),
            // A requested width is rounded up to one of these: fewer copies
            // in the CDN's cache.
            'widths' => [320, 640, 960, 1280, 1920],
            // imgproxy: hex key and salt (IMGPROXY_KEY / IMGPROXY_SALT).
            'key' => env('ATRIUM_IMGPROXY_KEY'),
            'salt' => env('ATRIUM_IMGPROXY_SALT'),
            // template: {url} {path} {width} {format} {quality}, e.g.
            // https://img.example.com/{width}x/{path}
            'template' => env('ATRIUM_CDN_IMAGES_TEMPLATE'),
        ],

        // Removing deleted files from the CDN's cache: null, cloudflare or
        // bunny. The owner creates the API token in the CDN's dashboard.
        'purge' => [
            'driver' => env('ATRIUM_CDN_PURGE'),
            'on_delete' => env('ATRIUM_CDN_PURGE_ON_DELETE', true),
            'cloudflare' => [
                'zone' => env('CLOUDFLARE_ZONE_ID'),
                // A token with the "Cache Purge" permission for the zone.
                'token' => env('CLOUDFLARE_API_TOKEN'),
            ],
            'bunny' => [
                // Account API key (Account settings → API).
                'key' => env('BUNNY_API_KEY'),
                // Pull zone id, for purging everything.
                'pull_zone' => env('BUNNY_PULL_ZONE_ID'),
            ],
        ],
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
    | Captcha
    |--------------------------------------------------------------------------
    |
    | One captcha for the public forms of every module (reviews, forms, ...):
    | a form draws <x-atrium::captcha /> and validates with
    | app(CaptchaManager::class)->rules().
    |
    | driver — none (off), image (built in: a sum drawn as a picture, no keys,
    | no outside service) or recaptcha (Google reCAPTCHA v2, keys from
    | https://www.google.com/recaptcha/admin; without them the image one is
    | used). Other kinds: CaptchaManager::extend().
    | image.ttl — minutes a question of the image captcha can be answered.
    |
    */
    'captcha' => [
        'driver' => env('ATRIUM_CAPTCHA', 'none'),
        'recaptcha' => [
            'site_key' => env('RECAPTCHA_SITE_KEY'),
            'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        ],
        'image' => [
            'ttl' => 10,
        ],
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
    | Dashboard gadgets (tiles). The panel and its modules register theirs by
    | key in `atrium.ui.gadgets` ('site-speed', 'performance', 'recent',
    | 'errors', 'monitoring'); `gadgets` is the layout. Each entry is a key,
    | a class name or an array with a `gadget` key plus instance options
    | ('title', 'width', 'model', 'count', 'enabled'). A registered tile the
    | layout does not name comes after the rest; switch one off with
    | ['gadget' => 'errors', 'enabled' => false].
    |
    | "Recent items" ('recent') needs a model, so it is named in the
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
            'site-speed',
            'performance',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Import and export
    |--------------------------------------------------------------------------
    |
    | Any module's records come in from CSV and Excel (.xlsx) files and go out
    | as the list shows them. A module opts out with isImportableInAdmin() /
    | isExportableInAdmin() on its model and adds its own columns to the
    | mapping with the `atrium.import.targets` filter.
    |
    | `directory` is where uploaded files wait for their import (read as they
    | were left by the upload, so a local folder; storage/app/private/atrium-imports
    | when empty); `max_size` the largest file
    | the panel takes, in kilobytes; `chunk` rows read from a file (or
    | records read for an export) at a time. An import works in steps of
    | `seconds` — a queue job, a run of the scheduler and the progress page
    | (`request_seconds`, without a worker) each save their place and let go,
    | so a large file never meets a time limit. `errors` rows with a problem
    | are listed in the report (all are counted). Images by URL: how long to
    | wait for one and how large it may be (KB).
    |
    */
    'import' => [
        'directory' => env('ATRIUM_IMPORT_DIRECTORY'),
        'max_size' => 51200,
        'chunk' => 100,
        'seconds' => 20,
        'request_seconds' => 5,
        'errors' => 500,
        'image_timeout' => 10,
        'image_max_size' => 10240,
    ],

    'export' => [
        'chunk' => 500,
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

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The record cache (docs/cache.md): lists answer with ids, records come
    | from cards built once per language, and saving a record in the admin
    | drops what showed it. Works on every Laravel store; Redis is the one to
    | use in production (one MGET per list, tags free memory at once).
    |
    | store     a store of config/cache.php, the default one when null
    | strategy  auto: tags where the store has them (Redis, Memcached),
    |           version keys elsewhere; versions: version keys everywhere
    | ttl       seconds: cards, lists of ids, full pages
    | time_step lists that compare with "now" (active from/to) see time in
    |           steps of this many seconds, so their keys stay the same
    | ignore    models whose saves never touch the cache
    | pages     full-page cache for visitors who are not logged in to the
    |           admin; off by default (see docs/cache.md before turning on)
    | debug     a line under public pages with queries and cache hits, in
    |           the local environment with APP_DEBUG on
    |
    */
    'cache' => [
        'enabled' => (bool) env('ATRIUM_CACHE_ENABLED', true),
        'store' => env('ATRIUM_CACHE_STORE'),
        'strategy' => env('ATRIUM_CACHE_STRATEGY', 'auto'),
        'ttl' => [
            'cards' => (int) env('ATRIUM_CACHE_TTL_CARDS', 86400),
            'lists' => (int) env('ATRIUM_CACHE_TTL_LISTS', 3600),
            'pages' => (int) env('ATRIUM_CACHE_TTL_PAGES', 600),
        ],
        'time_step' => 60,
        'stats' => true,
        'ignore' => [
            PageTiming::class,
        ],
        'pages' => [
            'enabled' => (bool) env('ATRIUM_PAGE_CACHE', false),
            // Seconds a CDN may keep a page without the session's form token.
            'shared_max_age' => (int) env('ATRIUM_PAGE_CACHE_SHARED_MAX_AGE', 60),
            // Paths never cached, as Request::is() patterns.
            'except' => [],
        ],
        'debug' => (bool) env('ATRIUM_CACHE_DEBUG', true),
    ],
];
