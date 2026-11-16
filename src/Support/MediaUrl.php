<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

/**
 * Address of a stored image or file, as the admin form shows it.
 *
 * Uploads through the admin are kept as paths on the public disk
 * (`articles/photo.jpg`, served from /storage). Files moved from another
 * site keep their own absolute path (`/upload/iblock/…`), and some values
 * are full URLs. All three end up here.
 */
final class MediaUrl
{
    public static function to(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = $path['url'] ?? $path['path'] ?? null;
        }

        if (! is_string($path) || ($path = trim($path)) === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        return asset(str_starts_with($path, '/') ? $path : 'storage/'.$path);
    }
}
