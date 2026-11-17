<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The "Made by AlveSoft Solutions" link is on by default, and a site can
 * switch it off with atrium.credit, because nothing in the MIT licence
 * requires it.
 */
final class CreditTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function settings(): array
    {
        return [
            'on' => [true, true],
            'off' => [false, false],
            'off from an env string' => ['', false],
            'zero' => [0, false],
            'one' => [1, true],
            'null, as an empty env value' => [null, false],
        ];
    }

    public function test_the_credit_is_shown_when_the_site_does_not_say(): void
    {
        $settings = config('atrium');
        unset($settings['credit']);
        config(['atrium' => $settings]);

        $this->assertTrue($this->get('/admin/login')->inertiaProps('credit'));
    }

    public function test_the_package_config_turns_the_credit_on_by_default(): void
    {
        $this->assertTrue(config('atrium.credit'));
    }

    #[DataProvider('settings')]
    public function test_the_sign_in_page_follows_the_setting(mixed $setting, bool $shown): void
    {
        config(['atrium.credit' => $setting]);

        $this->assertSame($shown, $this->get('/admin/login')->inertiaProps('credit'));
    }

    #[DataProvider('settings')]
    public function test_the_panel_follows_the_setting(mixed $setting, bool $shown): void
    {
        config(['atrium.credit' => $setting]);

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

        $response->assertOk();
        $this->assertSame($shown, $response->inertiaProps('credit'));
    }

    public function test_the_line_is_in_every_language_and_keeps_the_company_name(): void
    {
        foreach (array_keys(config('atrium.locales')) as $locale) {
            $line = trans('atrium::layout.made_by', [], $locale);

            $this->assertNotSame('atrium::layout.made_by', $line, "{$locale} has no credit line");
            $this->assertStringContainsString('AlveSoft Solutions', $line, "{$locale} translated the company name");

            if ($locale !== 'en') {
                $this->assertNotSame(trans('atrium::layout.made_by', [], 'en'), $line, "{$locale} shows the English line");
            }
        }
    }
}
