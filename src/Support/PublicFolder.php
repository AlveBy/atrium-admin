<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

/**
 * The folder the web server serves, whatever it is called.
 *
 * Many shared hostings serve a fixed folder (`public_html`, `www`) and the
 * site cannot be pointed at `public`, so `public` gets renamed. A request
 * knows its folder (`public/index.php` hands it to Laravel); the console does
 * not, and `storage:link` or the published admin bundle would land in a
 * `public` nobody serves. `bootstrap/app.php` asks this class before `.env`
 * is read, so it works the same for artisan, cron and a cached config.
 */
final class PublicFolder
{
    /**
     * `public` while it has its index.php; otherwise the only folder of the
     * project whose index.php starts this application. With none or several
     * of those it stays `public`, as in Laravel.
     */
    public static function find(string $basePath): string
    {
        $default = $basePath.'/public';

        if (is_file($default.'/index.php')) {
            return $default;
        }

        $found = [];

        foreach (glob($basePath.'/*/index.php') ?: [] as $index) {
            if (str_contains((string) @file_get_contents($index), '/bootstrap/app.php')) {
                $found[] = dirname($index);
            }
        }

        return count($found) === 1 ? $found[0] : $default;
    }

    /**
     * A file in the public folder as messages name it: `www/robots.txt`,
     * from the project folder.
     */
    public static function shown(string $file): string
    {
        $path = public_path($file);
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
