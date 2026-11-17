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
 * The AlveSoft credit shows the white logo where the background is dark and
 * the colour one where it is light. atrium.css lists the themes by hand, per
 * surface; this test works out from each theme's own colours which logo
 * stands out more, so a new theme cannot be left off the list.
 */
final class CreditLogoTest extends TestCase
{
    private const CSS = __DIR__.'/../../resources/css/atrium.css';

    /** The dark blue of "Alve" in the colour logo; the white logo has white there. */
    private const LOGO_BLUE = '#1e5aa8';

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function surfaces(): array
    {
        $cases = [];

        foreach (Themes::THEMES as $theme) {
            $cases["{$theme}: sidebar"] = [$theme, 'sidebar', 'sidebar'];
            $cases["{$theme}: sign-in page"] = [$theme, 'page', 'page'];
        }

        return $cases;
    }

    #[DataProvider('surfaces')]
    public function test_the_logo_that_stands_out_more_is_chosen(string $theme, string $surface, string $background): void
    {
        $colour = self::themes()[$theme][$background];
        $white = self::ratio('#ffffff', $colour) > self::ratio(self::LOGO_BLUE, $colour);

        $this->assertSame(
            $white,
            in_array($theme, self::whiteLogoThemes($surface), true),
            sprintf('%s: on the %s (%s) the %s logo stands out more; fix the list in atrium.css', $theme, $surface, $colour, $white ? 'white' : 'colour'),
        );
    }

    public function test_the_list_names_only_themes_the_panel_offers(): void
    {
        foreach (['sidebar', 'page'] as $surface) {
            $this->assertNotEmpty(self::whiteLogoThemes($surface), "no theme has the white logo on the {$surface}");
            $this->assertSame([], array_diff(self::whiteLogoThemes($surface), Themes::THEMES), "the {$surface} list names an unknown theme");
        }
    }

    public function test_both_logos_are_in_the_package_and_have_no_scripts(): void
    {
        foreach (['logo', 'logo-white'] as $name) {
            $svg = (string) file_get_contents(__DIR__."/../../resources/images/alvesoft/{$name}.svg");

            $this->assertStringStartsWith('<svg', $svg, "{$name}.svg is not an SVG");
            $this->assertStringContainsString("url('../images/alvesoft/{$name}.svg')", self::css(), "atrium.css does not use {$name}.svg");
            $this->assertDoesNotMatchRegularExpression('/<script|\son\w+\s*=|javascript:|<foreignObject|href\s*=/i', $svg, "{$name}.svg can run code or load something");
        }
    }

    /**
     * The themes atrium.css gives the white logo on a surface.
     *
     * @return list<string>
     */
    private static function whiteLogoThemes(string $surface): array
    {
        preg_match_all("/:where\\(([^)]*)\\)\\s*\\[data-credit-on='{$surface}'\\]/", self::css(), $lists);
        preg_match_all("/\\[data-theme='([a-z]+)'\\]/", implode(',', $lists[1]), $themes);

        return $themes[1];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function themes(): array
    {
        preg_match_all("/\\[data-theme='([a-z]+)'\\]\\s*\\{([^}]*)\\}/", self::css(), $blocks, PREG_SET_ORDER);

        $themes = [];

        foreach ($blocks as [, $theme, $body]) {
            preg_match_all('/--atrium-([\w-]+):\s*(#[0-9a-fA-F]{6});/', $body, $tokens, PREG_SET_ORDER);

            foreach ($tokens as [, $name, $value]) {
                $themes[$theme][$name] = strtolower($value);
            }
        }

        return $themes;
    }

    private static function css(): string
    {
        return (string) file_get_contents(self::CSS);
    }

    private static function ratio(string $a, string $b): float
    {
        [$light, $dark] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($light + 0.05) / ($dark + 0.05);
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(static function (string $pair): float {
            $channel = hexdec($pair) / 255;

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
