<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Support\Menu;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
