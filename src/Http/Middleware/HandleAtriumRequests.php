<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Locales;
use Atrium\Admin\Support\Menu;
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
                    ] : null,
                ];
            },
            'branding' => fn () => config('atrium.branding'),
            'locale' => fn () => app()->getLocale(),
            'locales' => fn () => app(Locales::class)->available(),
            // The languages the content is written in — for the switch on
            // forms with translatable fields.
            'contentLocales' => fn () => app(ContentLanguages::class)->all()
                ->map(fn ($language) => ['code' => $language->code, 'name' => $language->name, 'default' => $language->is_default])
                ->values()
                ->all(),
            // Sent once per language and kept by the client across visits.
            'translations' => Inertia::once(fn () => app(Locales::class)->dictionary(app()->getLocale()))
                ->as('atrium.translations.'.app()->getLocale()),
            'menu' => fn () => app(Menu::class)->items(),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ];
    }
}
