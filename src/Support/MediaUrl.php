<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Address of a stored image or file, as the admin form shows it.
 *
 * Uploads through the admin are kept as paths on the public disk
 * (`/storage/articles/photo.jpg`, or `articles/photo.jpg`), files moved from
 * another site keep their own absolute path (`/upload/iblock/…`), and some
 * values are full URLs (uploads to S3 and the like). All three end up here,
 * and go through the CDN when one is set (atrium.cdn).
 */
final class MediaUrl
{
    /**
     * @param  int|null  $width  wanted width in pixels: the CDN or imgproxy
     *                           makes it when an image driver is set
     */
    public static function to(mixed $path, ?int $width = null): ?string
    {
        if (is_array($path)) {
            $path = $path['url'] ?? $path['path'] ?? null;
        }

        if (! is_string($path) || ($path = trim($path)) === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $path)) {
            $url = $path;
        } else {
            $path = str_starts_with($path, '/') ? $path : '/storage/'.$path;
            $url = Cdn::serves($path) ? Cdn::asset($path) : asset($path);
        }

        return $width === null ? $url : Cdn::image($url, $width);
    }

    /**
     * What to keep in the database for a file just stored on a disk: a path
     * from the web root for this server's public disk (host-independent), the
     * disk's own address for a remote one (S3: AWS_URL, set to the CDN).
     */
    public static function forStored(string $disk, string $path): string
    {
        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            return '/storage/'.ltrim($path, '/');
        }

        return Storage::disk($disk)->url($path);
    }
}
