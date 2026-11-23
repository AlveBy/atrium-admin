<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Support\Themes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * WCAG contrast of every theme, read from the stylesheet the panel is built
 * from. Change a colour in resources/css/atrium.css and this test measures
 * the new value: text needs 4.5:1, borders, bars, rings and the logo 3:1.
 */
final class ThemeContrastTest extends TestCase
{
    private const CSS = __DIR__.'/../../resources/css/atrium.css';

    /** Text against what it sits on: 4.5:1 (WCAG 1.4.3). */
    private const TEXT = [
        'menu text on the sidebar' => ['sidebar-fg', 'sidebar'],
        'menu text on a hovered entry' => ['sidebar-fg', 'sidebar-hover'],
        'muted sidebar text' => ['sidebar-muted', 'sidebar'],
        'selected menu entry' => ['sidebar-active-fg', 'sidebar-active'],
        'text on a card' => ['fg', 'surface'],
        'text on the page' => ['fg', 'page'],
        'text in a table header' => ['fg', 'surface-alt'],
        'text on a hovered row' => ['fg', 'hover'],
        'secondary text on a card' => ['muted', 'surface'],
        'secondary text on the page' => ['muted', 'page'],
        'secondary text in a table header' => ['muted', 'surface-alt'],
        'secondary text on a hovered row' => ['muted', 'hover'],
        'button text' => ['on-accent', 'accent'],
        'hovered button text' => ['on-accent', 'accent-hover'],
        'link on a card' => ['accent', 'surface'],
        'link on the page' => ['accent', 'page'],
        'hovered link' => ['accent-hover', 'surface'],
        'link on a soft background' => ['accent-hover', 'accent-soft'],
        '"Active" badge' => ['accent-soft-fg', 'accent-soft'],
        '"Hidden" badge' => ['off-fg', 'off'],
        'error text on a card' => ['danger', 'surface'],
        'error text on the page' => ['danger', 'page'],
        'delete button text' => ['on-danger', 'danger'],
        'hovered delete button text' => ['on-danger', 'danger-hover'],
        'error message box' => ['danger-soft-fg', 'danger-soft'],
        'success text on a card' => ['success', 'surface'],
        'success message box' => ['success-soft-fg', 'success-soft'],
        'warning message box' => ['warning-soft-fg', 'warning-soft'],
    ];

    /** Shapes that carry meaning, against their background: 3:1 (WCAG 1.4.11). */
    private const UI = [
        'selection bar on the sidebar' => ['sidebar-accent', 'sidebar'],
        'muted menu icon on a hovered entry' => ['sidebar-muted', 'sidebar-hover'],
        'input border on a card' => ['field', 'surface'],
        'input border on the page' => ['field', 'page'],
        'input border in a table header' => ['field', 'surface-alt'],
        'focus ring on a card' => ['focus', 'surface'],
        'focus ring on the page' => ['focus', 'page'],
        'button against the page' => ['accent', 'page'],
        'error border' => ['danger', 'surface'],
        'logo on the sidebar' => ['mark', 'sidebar'],
        'logo accent on the sidebar' => ['mark-accent', 'sidebar'],
        'logo on the sign-in page' => ['fg', 'page'],
        'logo accent on the sign-in page' => ['accent', 'page'],
        'AI helper on the page' => ['fg', 'page'],
        'AI helper over a card' => ['fg', 'surface'],
        'AI helper\'s eyes and brows on its body' => ['surface', 'fg'],
        'AI helper\'s mouth on the page' => ['accent', 'page'],
        'AI helper\'s mouth over a card' => ['accent', 'surface'],
    ];

    /**
     * @return array<string, array{string, string, string, float}>
     */
    public static function pairs(): array
    {
        $cases = [];

        foreach (array_keys(self::themes()) as $theme) {
            foreach ([[self::TEXT, 4.5], [self::UI, 3.0]] as [$pairs, $minimum]) {
                foreach ($pairs as $what => [$foreground, $background]) {
                    $cases["{$theme}: {$what}"] = [$theme, $foreground, $background, $minimum];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('pairs')]
    public function test_the_pair_has_enough_contrast(string $theme, string $foreground, string $background, float $minimum): void
    {
        $tokens = self::themes()[$theme];
        $ratio = self::ratio($tokens[$foreground], $tokens[$background]);

        $this->assertGreaterThanOrEqual(
            $minimum,
            $ratio,
            sprintf('%s: %s %s on %s %s is %.2f:1, needs %.1f:1', $theme, $foreground, $tokens[$foreground], $background, $tokens[$background], $ratio, $minimum),
        );
    }

    public function test_the_stylesheet_has_exactly_the_themes_the_panel_offers(): void
    {
        $this->assertSame(Themes::THEMES, array_keys(self::themes()));
    }

    public function test_every_theme_sets_every_colour_the_utilities_use(): void
    {
        preg_match_all('/--color-[\w-]+:\s*var\(--atrium-([\w-]+)\)/', (string) file_get_contents(self::CSS), $matches);
        $used = $matches[1];
        sort($used);

        $this->assertNotEmpty($used);

        foreach (self::themes() as $theme => $tokens) {
            $own = array_keys($tokens);
            sort($own);

            $this->assertSame($used, $own, "{$theme} must set exactly the colours the utilities read");
        }
    }

    public function test_the_ratio_matches_known_values(): void
    {
        $this->assertEqualsWithDelta(21.0, self::ratio('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, self::ratio('#777777', '#777777'), 0.01);
        $this->assertEqualsWithDelta(4.83, self::ratio('#6b7280', '#ffffff'), 0.01);
    }

    /**
     * Theme name => [token => #rrggbb], from the `[data-theme='…']` blocks.
     *
     * @return array<string, array<string, string>>
     */
    private static function themes(): array
    {
        preg_match_all("/\[data-theme='([a-z]+)'\]\s*\{([^}]*)\}/", (string) file_get_contents(self::CSS), $blocks, PREG_SET_ORDER);

        $themes = [];

        foreach ($blocks as [, $theme, $body]) {
            preg_match_all('/--atrium-([\w-]+):\s*([^;]+);/', $body, $tokens, PREG_SET_ORDER);

            foreach ($tokens as [, $name, $value]) {
                $themes[$theme][$name] = strtolower(trim($value));
            }
        }

        return $themes;
    }

    private static function ratio(string $a, string $b): float
    {
        if ($a === 'transparent' || $b === 'transparent') {
            throw new \InvalidArgumentException('A transparent colour has no contrast of its own.');
        }

        $lighter = max(self::luminance($a), self::luminance($b));
        $darker = min(self::luminance($a), self::luminance($b));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /** Relative luminance, WCAG 2.x. */
    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = array_map(static function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
