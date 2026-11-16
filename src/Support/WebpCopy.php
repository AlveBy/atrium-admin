<?php

declare(strict_types=1);

namespace Atrium\Admin\Support;

use GdImage;

/**
 * A lighter copy of an image: `photo.jpg` gets a `photo.jpg.webp` next to it.
 *
 * The web server serves it by the Accept header (rule in `public/.htaccess`),
 * so paths change neither in the database nor in templates — which matters:
 * images drawn via `background-image` (hero banners, gallery tiles) cannot use
 * `<picture>`.
 *
 * The name is built by appending the extension, not replacing it: the file name
 * shows what the copy was made from, and the rewrite rule fits in one line.
 *
 * Copies used to be made only by the console command, so an image uploaded in
 * the panel was served heavy until the next scheduled run. Now uploads use the
 * same code.
 */
final class WebpCopy
{
    /** Nothing above 40 Mpx: such sources exhaust PHP memory. */
    private const MAX_PIXELS = 40_000_000;

    public const EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /** Whether this PHP build can write webp. */
    public static function supported(): bool
    {
        return function_exists('imagewebp');
    }

    /** Whether to try at all: the extension is one we compress. */
    public static function convertible(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /**
     * Make the copy.
     *
     * Returns more than yes/no: the `images:webp` command builds its report
     * from these states and needs to tell "copy already existed" from "image
     * could not be opened".
     *
     * - made     — copy made, before/after hold the sizes
     * - skipped  — copy exists and is not older than the original
     * - rejected — copy came out no lighter than the original, deleted
     * - failed   — could not open, too large, or webp cannot be written
     *
     * @return array{status: string, before?: int, after?: int}
     */
    public static function make(string $source, int $quality = 82, int $maxWidth = 1920, bool $force = false): array
    {
        if (! self::supported() || ! is_file($source) || ! self::convertible($source)) {
            return ['status' => 'failed'];
        }

        $target = $source.'.webp';

        if (! $force && is_file($target) && filemtime($target) >= filemtime($source)) {
            return ['status' => 'skipped'];
        }

        $image = self::load($source);

        if ($image === null) {
            return ['status' => 'failed'];
        }

        $image = self::resize($image, $maxWidth);
        $ok = @imagewebp($image, $target, $quality);
        imagedestroy($image);

        if (! $ok) {
            return ['status' => 'failed'];
        }

        $before = (int) filesize($source);
        $after = (int) filesize($target);

        // A copy heavier than the source is pointless — the server would serve
        // the original. Happens with palette PNGs: webp loses on badges and logos.
        if ($after >= $before) {
            @unlink($target);

            return ['status' => 'rejected'];
        }

        return ['status' => 'made', 'before' => $before, 'after' => $after];
    }

    private static function load(string $path): ?GdImage
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;

        if ($width * $height > self::MAX_PIXELS) {
            return null;
        }

        // Some files named .jpg are actually webp already (left so by an old
        // CMS module). Nothing to compress a second time.
        if ($info[2] === IMAGETYPE_WEBP) {
            return null;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => false,
        };

        if ($image === false) {
            return null;
        }

        // Otherwise palette PNGs lose colours when scaled, and without these two
        // lines transparency is filled with black.
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    private static function resize(GdImage $image, int $maxWidth): GdImage
    {
        $width = imagesx($image);

        if ($maxWidth <= 0 || $width <= $maxWidth) {
            return $image;
        }

        $height = (int) round(imagesy($image) * $maxWidth / $width);
        $resized = imagecreatetruecolor($maxWidth, $height);

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $height, $width, imagesy($image));
        imagedestroy($image);

        return $resized;
    }
}
