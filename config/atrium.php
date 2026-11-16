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
        | Подтверждение входа кодом с почты.
        |
        | Пароль один и живёт, пока его не сменят; код приходит на ящик и
        | действует минуты. Выключать стоит только там, где почты нет —
        | например в браузерных тестах.
        |
        | ttl — сколько минут годен код, resend_after — через сколько секунд
        | можно попросить новое письмо.
        */
        'two_factor' => [
            'enabled' => (bool) env('ATRIUM_2FA_ENABLED', true),
            'ttl' => (int) env('ATRIUM_2FA_TTL', 10),
            'resend_after' => (int) env('ATRIUM_2FA_RESEND_AFTER', 60),
        ],
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
    |
    */
    'menu' => [
        [
            'label' => 'Dashboard',
            'route' => 'admin.dashboard',
            'icon' => 'home',
        ],
        [
            'label' => 'Administrators',
            'route' => 'admin.users.index',
            'icon' => 'users',
        ],
        [
            'label' => 'API tokens',
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

        // Делать ли рядом с загруженной картинкой облегчённую webp-копию.
        // Отдаёт её веб-сервер сам, по заголовку Accept — см. public/.htaccess.
        // Выключать имеет смысл только там, где такого правила нет: копии
        // будут занимать место, а посетителю не поедут.
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
            //     'title' => '{name} - купить в интернет-магазине',
            //     'description' => '{name}. {preview_text}',
            // ],
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
    | Рабочий стол — плитки-гаджеты, как на стартовой странице админки
    | Битрикса. Каждый элемент `gadgets` — либо имя класса, либо массив с
    | ключом `gadget` и настройками экземпляра ('title', 'width', 'model',
    | 'count', 'enabled').
    |
    | Здесь только те гаджеты, которым не нужно знать про модели проекта.
    | Гаджет «Элементы инфоблока» добавляется в конфиг приложения, где эти
    | модели уже есть.
    |
    | `timings` — сбор времени генерации страниц публичной части, из него
    | живут оба «зелёных» гаджета. Выключается целиком, вместе с middleware.
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
    | Модули админки через API — для внешних программ и ИИ-агентов. Токены
    | выпускаются в разделе «API-токены», документация открыта по адресу
    | `prefix` (Markdown) и `prefix/openapi.json`.
    |
    | Модули в API попадают сами — всё, что зарегистрировано в админке.
    | `except` — ключи модулей, которых в API не должно быть никогда:
    | администраторов через токен не заводят.
    |
    */
    'api' => [
        'prefix' => 'api/v1',
        'except' => ['users'],
    ],
];
