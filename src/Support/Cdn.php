<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

/**
 * Addresses through the CDN (`atrium.cdn`): files of this site, resized
 * images and the HTML of texts. Everything returns its input unchanged while
 * the CDN is off, so callers never need to ask.
 */
final class Cdn
{
    public static function enabled(): bool
    {
        return self::base() !== null;
    }

    /** https://cdn.example.com, or null when off. */
    public static function base(): ?string
    {
        $url = config('atrium.cdn.url');

        return is_string($url) && ($url = rtrim(trim($url), '/')) !== '' ? $url : null;
    }

    /**
     * A file of this site by its path from the web root (/storage/a.jpg):
     * on the CDN with ?v=, or from this site as before.
     */
    public static function asset(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $base = self::base();

        if ($base === null) {
            return asset($path);
        }

        return $base.self::encode($path).self::version($path);
    }

    /**
     * The same image at a width, made by the CDN or imgproxy. Unchanged when
     * no image driver is set or the address is not one the driver can reach.
     */
    public static function image(string $url, int $width): string
    {
        $driver = self::driver();

        if ($driver === null || ! self::resizable($url)) {
            return $url;
        }

        $width = self::width($width);
        $format = config('atrium.cdn.images.format');
        $format = in_array($format, ['webp', 'avif'], true) ? $format : 'auto';
        $quality = (int) config('atrium.cdn.images.quality');

        return match ($driver) {
            'cloudflare' => self::cloudflare($url, $width, $format, $quality),
            'bunny' => self::bunny($url, $width, $quality),
            'imgproxy' => self::imgproxy($url, $width, $format, $quality),
            'template' => self::template($url, $width, $format, $quality),
        };
    }

    /**
     * srcset for an image at the configured widths up to $max, or null when
     * there is no image driver.
     */
    public static function srcset(string $url, ?int $max = null): ?string
    {
        if (self::driver() === null || ! self::resizable($url)) {
            return null;
        }

        $set = [];

        foreach (self::widths() as $width) {
            if ($max === null || $width <= $max) {
                $set[] = self::image($url, $width).' '.$width.'w';
            }
        }

        return $set === [] ? null : implode(', ', $set);
    }

    /**
     * Images and links inside a text (src="/storage/…") through the CDN.
     */
    public static function html(string $html): string
    {
        if (! self::enabled() || $html === '') {
            return $html;
        }

        $prefixes = array_map(static fn (string $prefix): string => preg_quote($prefix, '#'), self::paths());

        if ($prefixes === []) {
            return $html;
        }

        return (string) preg_replace_callback(
            '#(\s(?:src|href|poster)\s*=\s*)(["\'])((?:'.implode('|', $prefixes).')[^"\'?\#]*)\2#i',
            static fn (array $match): string => $match[1].$match[2].self::asset(rawurldecode($match[3])).$match[2],
            $html,
        );
    }

    /**
     * Whether a root-relative path goes through the CDN.
     */
    public static function serves(string $path): bool
    {
        foreach (self::paths() as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<array-key,string>
     */
    private static function paths(): array
    {
        return array_filter((array) config('atrium.cdn.paths'), 'is_string');
    }

    /** @return 'cloudflare'|'bunny'|'imgproxy'|'template'|null */
    private static function driver(): ?string
    {
        $driver = config('atrium.cdn.images.driver');

        return in_array($driver, ['cloudflare', 'bunny', 'imgproxy', 'template'], true) ? $driver : null;
    }

    /**
     * @return array<int,int>
     */
    private static function widths(): array
    {
        $widths = array_filter(array_map('intval', (array) config('atrium.cdn.images.widths')), static fn (int $width): bool => $width > 0);
        sort($widths);

        return $widths === [] ? [320, 640, 960, 1280, 1920] : $widths;
    }

    /** Rounded up to the nearest preset, so the CDN caches few copies. */
    private static function width(int $width): int
    {
        foreach (self::widths() as $preset) {
            if ($preset >= $width) {
                return $preset;
            }
        }

        return max(self::widths());
    }

    private static function version(string $path): string
    {
        if (! config('atrium.cdn.version', true)) {
            return '';
        }

        $file = public_path($path);
        $time = is_file($file) ? @filemtime($file) : false;

        return $time === false ? '' : '?v='.base_convert((string) $time, 10, 36);
    }

    /**
     * Spaces and the like in file names, kept readable otherwise. Stored
     * paths may already be encoded (manual%20v2.pdf): never twice.
     */
    private static function encode(string $path): string
    {
        return implode('/', array_map(static fn (string $part): string => rawurlencode(rawurldecode($part)), explode('/', $path)));
    }

    /**
     * Cloudflare and Bunny resize only what passes through them; imgproxy
     * and a template fetch any address.
     */
    private static function resizable(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (! preg_match('#\.(jpe?g|png|webp|avif|gif)$#i', $path)) {
            return false;
        }

        return match (config('atrium.cdn.images.driver')) {
            'cloudflare', 'bunny' => self::onCdn($url),
            default => true,
        };
    }

    private static function onCdn(string $url): bool
    {
        foreach (array_filter([self::base(), self::imagesBase()]) as $base) {
            if (str_starts_with($url, $base.'/')) {
                return true;
            }
        }

        return false;
    }

    private static function imagesBase(): ?string
    {
        $url = config('atrium.cdn.images.url');

        return is_string($url) && ($url = rtrim(trim($url), '/')) !== '' ? $url : null;
    }

    /** /cdn-cgi/image/width=640,quality=82,format=auto/storage/a.jpg */
    private static function cloudflare(string $url, int $width, string $format, int $quality): string
    {
        $base = self::imagesBase() ?? self::base() ?? '';
        $source = str_starts_with($url, $base.'/') ? substr($url, strlen($base)) : $url;

        return $base.'/cdn-cgi/image/width='.$width.',quality='.$quality.',format='.$format.'/'.ltrim($source, '/');
    }

    /** Bunny Optimizer: the format follows the pull zone's WebP/AVIF setting. */
    private static function bunny(string $url, int $width, int $quality): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'width='.$width.'&quality='.$quality;
    }

    /**
     * /{signature}/rs:fit:640:0/q:82/plain/{source}@webp — signed when a key
     * and salt are set, "insecure" otherwise.
     */
    private static function imgproxy(string $url, int $width, string $format, int $quality): string
    {
        $source = url($url);
        $path = '/rs:fit:'.$width.':0/q:'.$quality.'/plain/'.rawurlencode($source).(in_array($format, ['webp', 'avif'], true) ? '@'.$format : '');
        // Hex, as imgproxy takes them; anything else would sign wrongly.
        $key = @hex2bin((string) config('atrium.cdn.images.key'));
        $salt = @hex2bin((string) config('atrium.cdn.images.salt'));
        $signature = 'insecure';

        if (is_string($key) && is_string($salt) && $key !== '' && $salt !== '') {
            $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $salt.$path, $key, true)), '+/', '-_'), '=');
        }

        return (self::imagesBase() ?? '').'/'.$signature.$path;
    }

    private static function template(string $url, int $width, string $format, int $quality): string
    {
        $source = url($url);

        return strtr((string) config('atrium.cdn.images.template', '{url}'), [
            '{url}' => rawurlencode($source),
            '{path}' => ltrim((string) parse_url($source, PHP_URL_PATH), '/'),
            '{width}' => $width,
            '{format}' => $format,
            '{quality}' => $quality,
        ]);
    }
}
