<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use GdImage;
use SplFileInfo;

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
    public static function make(string $source, int $quality = 82, int $maxWidth = 1920, bool $force = false, int $maxPixels = self::MAX_PIXELS): array
    {
        if (! self::supported() || ! is_file($source) || ! self::convertible($source)) {
            return ['status' => 'failed'];
        }

        $target = $source.'.webp';

        if (! $force && is_file($target) && filemtime($target) >= filemtime($source)) {
            return ['status' => 'skipped'];
        }

        $image = self::load($source, $maxPixels);

        if ($image === null) {
            return ['status' => 'failed'];
        }

        $image = self::resize($image, $maxWidth);
        $ok = @imagewebp($image, $target, $quality);

        if (! $ok) {
            return ['status' => 'failed'];
        }

        $before = (new SplFileInfo($source))->getSize();
        $after = (new SplFileInfo($target))->getSize();

        // A copy heavier than the source is pointless — the server would serve
        // the original. Happens with palette PNGs: webp loses on badges and logos.
        if ($after >= $before) {
            @unlink($target);

            return ['status' => 'rejected'];
        }

        return ['status' => 'made', 'before' => $before, 'after' => $after];
    }

    private static function load(string $path, int $maxPixels): ?GdImage
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;

        if ($width * $height > $maxPixels) {
            return null;
        }

        // Some files named .jpg are actually webp already (left so by an old
        // CMS module). Nothing to compress a second time: only the two types
        // below are opened.
        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => false,
        };

        if ($image === false) {
            return null;
        }

        // Otherwise palette PNGs lose colours when scaled, and webp cannot be
        // written from a palette image at all.
        imagepalettetotruecolor($image);

        return $image;
    }

    private static function resize(GdImage $image, int $maxWidth): GdImage
    {
        $width = imagesx($image);
        $scaled = $maxWidth > 0 ? min($maxWidth, $width) : $width;

        return $scaled === $width ? $image : self::scaled($image, $scaled);
    }

    private static function scaled(GdImage $image, int $width): GdImage
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $height = max(1, (int) round($sourceHeight * $width / $sourceWidth));
        $resized = imagecreatetruecolor($width, $height);

        // Without this the copy of transparent areas is blended onto black.
        imagealphablending($resized, false);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        return $resized;
    }
}
