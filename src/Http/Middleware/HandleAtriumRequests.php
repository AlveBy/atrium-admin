<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Locales;
use Atrium\Admin\Support\Menu;
use Atrium\Admin\Support\Sites;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inertia for the admin panel only.
 *
 * The panel has its own root view and its own prebuilt bundle, so it does not
 * care whether the host application uses Inertia, Livewire or plain Blade, and
 * its shared props never leak into the application's own Inertia pages.
 */
final class HandleAtriumRequests extends Middleware
{
    protected $rootView = 'atrium::app';

    /**
     * The panel speaks the admin's language; the site keeps its own locale.
     * Set here, before any controller builds field labels or flash messages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->guard(config('atrium.auth.guard', 'admin'))->user();

        $locales = app(Locales::class);
        $locale = $locales->resolve($request, $user instanceof AdminUser ? $user : null);

        app()->setLocale($locale);
        $locales->fillValidationGaps($locale);

        return parent::handle($request, $next);
    }

    public function version(Request $request): ?string
    {
        $manifest = public_path('vendor/atrium/manifest.json');

        return is_file($manifest) ? (string) md5_file($manifest) : null;
    }

    /**
     * @return array<string,mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => function () {
                $user = auth()->guard(config('atrium.auth.guard', 'admin'))->user();

                return [
                    'user' => $user ? [
                        'id' => $user->getKey(),
                        'name' => $user->name,
                        'email' => $user->email,
                        'is_superadmin' => $user instanceof AdminUser && $user->isSuperadmin(),
                    ] : null,
                ];
            },
            'branding' => fn () => config('atrium.branding'),
            'locale' => fn () => app()->getLocale(),
            'locales' => fn () => app(Locales::class)->available(),
            // The languages the content is written in — for the switch on
            // forms with translatable fields.
            'contentLocales' => fn () => app(ContentLanguages::class)->all()
                ->map(fn ($language) => ['code' => $language->code, 'name' => $language->name, 'default' => $language->code === app(ContentLanguages::class)->default()])
                ->values()
                ->all(),
            // The header's site switch: the sites this admin may work with.
            'sites' => fn () => $this->sites($request),
            'currentSite' => fn () => [
                'id' => app(Sites::class)->current()->id,
                'name' => app(Sites::class)->current()->name,
                'url' => app(Sites::class)->current()->url(),
            ],
            // Sent once per language and kept by the client across visits.
            'translations' => Inertia::once(fn () => app(Locales::class)->dictionary(app()->getLocale()))
                ->as('atrium.translations.'.app()->getLocale()),
            // Only what the administrator's roles open.
            'menu' => fn () => app(Menu::class)->items($this->admin($request)),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ];
    }

    private function admin(Request $request): ?AdminUser
    {
        $user = $request->user(config('atrium.auth.guard', 'admin'));

        return $user instanceof AdminUser ? $user : null;
    }

    /**
     * @return array<int,array{id:int|null,name:string}>
     */
    private function sites(Request $request): array
    {
        $user = $this->admin($request);
        $sites = $user !== null ? $user->allowedSites() : app(Sites::class)->all();

        return $sites->map(fn ($site) => ['id' => $site->id, 'name' => $site->name])->values()->all();
    }
}
