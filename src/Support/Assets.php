<?php

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\HtmlString;

/**
 * Script and style tags of the prebuilt admin bundle.
 *
 * The bundle is published to public/vendor/atrium by `atrium:install`. While
 * developing Atrium itself, `npm run dev` inside packages/admin writes a hot
 * file there and the tags point to the Vite dev server instead.
 *
 * A clone of the application's Vite: the original keeps its own build
 * directory and hot file, and `withoutVite()` in tests still applies.
 */
final class Assets
{
    public const BUILD_DIRECTORY = 'vendor/atrium';

    public static function tags(): HtmlString
    {
        $vite = (clone app(Vite::class))
            ->useBuildDirectory(self::BUILD_DIRECTORY)
            ->useHotFile(public_path(self::BUILD_DIRECTORY.'/hot'));

        try {
            return $vite(['resources/js/app.js']);
        } catch (ViteManifestNotFoundException $e) {
            throw new ViteManifestNotFoundException(
                'Atrium assets are not published. Run `php artisan atrium:install` (or `php artisan vendor:publish --tag=atrium-assets --force`).',
                previous: $e,
            );
        }
    }
}
