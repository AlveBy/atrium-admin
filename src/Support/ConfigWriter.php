<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

/**
 * Small, conservative edits of the published config/atrium.php.
 *
 * Only touches the `'resources' => [`, `'modules' => [` and
 * `'disabled_modules' => [` lists and only
 * when it can find them; otherwise it reports failure and the caller tells
 * the user what to add. A config published before modules existed gets the
 * `'modules'` list right after `'resources'`.
 */
final class ConfigWriter
{
    public static function addResource(string $class, ?string $path = null): bool
    {
        $path ??= config_path('atrium.php');

        if (! is_file($path)) {
            return false;
        }

        $config = (string) file_get_contents($path);
        $entry = '\\'.ltrim($class, '\\').'::class';

        if (str_contains($config, $entry)) {
            return true;
        }

        if (! preg_match("/'resources'\s*=>\s*\[\n/", $config, $match, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $at = $match[0][1] + strlen($match[0][0]);
        $config = substr($config, 0, $at)."        {$entry},\n".substr($config, $at);

        self::write($path, $config);

        return true;
    }

    /**
     * Add a package name to `'modules' => [`, the installed modules.
     */
    public static function addModule(string $name, ?string $path = null): bool
    {
        return self::addToList('modules', 'resources', 'Installed module packages (atrium:module:install).', $name, $path);
    }

    /**
     * Take a package name out of `'modules' => [`. True when it is not
     * there (any more).
     */
    public static function removeModule(string $name, ?string $path = null): bool
    {
        return self::removeFromList('modules', $name, $path);
    }

    /**
     * Add a package name to `'disabled_modules' => [`: installed, switched
     * off.
     */
    public static function disableModule(string $name, ?string $path = null): bool
    {
        return self::addToList('disabled_modules', 'modules', 'Installed modules switched off on the Modules screen.', $name, $path);
    }

    public static function enableModule(string $name, ?string $path = null): bool
    {
        return self::removeFromList('disabled_modules', $name, $path);
    }

    /**
     * A config published before the list existed gets it right after the
     * `$after` list.
     */
    private static function addToList(string $key, string $after, string $comment, string $name, ?string $path): bool
    {
        $path ??= config_path('atrium.php');

        if (! is_file($path)) {
            return false;
        }

        $config = (string) file_get_contents($path);
        $entry = var_export(strtolower($name), true);
        $list = "/'{$key}'\\s*=>\\s*\\[\\n(.*?)^[ \\t]*\\],/sm";

        if (! preg_match($list, $config)) {
            if (! preg_match("/\\n    '{$after}'\\s*=>\\s*\\[.*?\\n    \\],\\n/s", $config, $match, PREG_OFFSET_CAPTURE)) {
                return false;
            }

            $at = $match[0][1] + strlen($match[0][0]);
            $config = substr($config, 0, $at)."\n    // {$comment}\n    '{$key}' => [\n    ],\n".substr($config, $at);
        }

        preg_match($list, $config, $match, PREG_OFFSET_CAPTURE);

        if (preg_match('/^[ \\t]*'.preg_quote($entry, '/').',/m', $match[1][0])) {
            return true;
        }

        // Appended: the list is the menu's order.
        $at = $match[1][1] + strlen($match[1][0]);
        self::write($path, substr($config, 0, $at)."        {$entry},\n".substr($config, $at));

        return true;
    }

    private static function removeFromList(string $key, string $name, ?string $path): bool
    {
        $path ??= config_path('atrium.php');

        if (! is_file($path)) {
            return false;
        }

        $config = (string) file_get_contents($path);

        if (! preg_match("/'{$key}'\s*=>\s*\[\n.*?\],/s", $config, $match, PREG_OFFSET_CAPTURE)) {
            return true;
        }

        $line = '/^[ \t]*'.preg_quote(var_export(strtolower($name), true), '/').',[ \t]*\n/m';
        $list = (string) preg_replace($line, '', $match[0][0]);

        self::write($path, substr_replace($config, $list, $match[0][1], strlen($match[0][0])));

        return true;
    }

    private static function write(string $path, string $config): void
    {
        file_put_contents($path, $config);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }
    }
}
