<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Support\Locales;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\TestCase;

final class LocaleTest extends TestCase
{
    use PinsExtensionSurface;

    public function test_a_guest_gets_the_browser_language_when_the_panel_has_it(): void
    {
        $response = $this->get('/admin/login', ['Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8']);

        $this->assertSame('ru', $response->inertiaProps('locale'));
        $this->assertSame('Сохранить', $response->inertiaProps('translations')['common.save']);
    }

    public function test_an_unknown_browser_language_falls_back_to_the_configured_default(): void
    {
        config(['atrium.locale' => 'de']);

        $response = $this->get('/admin/login', ['Accept-Language' => 'sw-KE,sw;q=0.9']);

        $this->assertSame('de', $response->inertiaProps('locale'));
    }

    public function test_a_default_that_is_not_text_or_not_available_falls_back_to_english(): void
    {
        foreach ([null, 5, 'xx'] as $configured) {
            config(['atrium.locale' => $configured]);

            $response = $this->get('/admin/login', ['Accept-Language' => 'sw-KE,sw;q=0.9']);

            $this->assertSame('en', $response->inertiaProps('locale'), var_export($configured, true));
        }
    }

    public function test_regional_codes_are_matched(): void
    {
        $this->assertSame('pt_BR', $this->get('/admin/login', ['Accept-Language' => 'pt-BR'])->inertiaProps('locale'));
        $this->assertSame('zh_CN', $this->get('/admin/login', ['Accept-Language' => 'zh-CN,zh;q=0.9'])->inertiaProps('locale'));
    }

    public function test_the_switch_on_the_sign_in_page_is_remembered_for_the_session(): void
    {
        $this->from('/admin/login')->post('/admin/locale', ['locale' => 'uk'])->assertRedirect('/admin/login');

        $this->assertSame('uk', $this->get('/admin/login')->inertiaProps('locale'));
    }

    public function test_a_language_is_required(): void
    {
        $this->from('/admin/login')->post('/admin/locale', [])->assertSessionHasErrors('locale');
    }

    public function test_an_admin_keeps_the_language_on_the_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->from('/admin')->post('/admin/locale', ['locale' => 'ja'])->assertRedirect('/admin');

        $this->assertSame('ja', $admin->fresh()->locale);

        // A new browser (no session) still opens in the admin's language.
        $this->flushSession();
        $this->assertSame('ja', $this->actingAs($admin->fresh(), 'admin')->get('/admin', ['Accept-Language' => 'en'])->inertiaProps('locale'));
    }

    public function test_server_side_text_follows_the_language(): void
    {
        $admin = $this->admin();
        $admin->update(['locale' => 'ru']);

        $menu = $this->actingAs($admin, 'admin')->get('/admin')->inertiaProps('menu');

        $this->assertSame(__('atrium::menu.dashboard', [], 'ru'), $menu[0]['label']);
        $this->assertNotSame(__('atrium::menu.dashboard', [], 'en'), $menu[0]['label']);
    }

    public function test_validation_messages_follow_the_language_when_the_app_has_none(): void
    {
        $admin = $this->admin();
        $admin->update(['locale' => 'ru']);

        $this->actingAs($admin, 'admin')
            ->from('/admin/posts/create')
            ->post('/admin/posts', ['name' => ''])
            ->assertSessionHasErrors(['name' => str_replace(':attribute', 'Name', __('atrium::validation.required', [], 'ru'))]);
    }

    public function test_the_application_s_own_validation_lines_win(): void
    {
        app('translator')->addLines(['validation.required' => 'Own: :attribute'], 'ru');
        $admin = $this->admin();
        $admin->update(['locale' => 'ru']);

        $this->actingAs($admin, 'admin')
            ->from('/admin/posts/create')
            ->post('/admin/posts', ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Own: Name']);
    }

    public function test_a_language_that_is_not_listed_is_refused(): void
    {
        $this->from('/admin/login')->post('/admin/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');

        $this->assertNull(session(Locales::SESSION_KEY));
    }

    public function test_missing_keys_fall_back_to_english(): void
    {
        $dictionary = app(Locales::class)->dictionary('xx');

        $this->assertSame('Save', $dictionary['common.save']);
    }
}
