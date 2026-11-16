<?php

declare(strict_types=1);

namespace Atrium\Admin\Support;

/**
 * Small, conservative edits of the published config/atrium.php.
 *
 * Only touches the `'resources' => [` list and only when it can find it;
 * otherwise it reports failure and the caller tells the user what to add.
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

        file_put_contents($path, $config);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        return true;
    }
}
