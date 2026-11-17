<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Captcha\CaptchaManager;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Http\Middleware\CachePages;
use Atrium\Admin\Modules\Settings;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Illuminate\Routing\Router;

/**
 * What the cache promised other parts of Atrium: no page kept that holds
 * something made for one visitor, the request's numbers for monitoring,
 * module settings read without a query.
 */
class CacheContractsTest extends CacheTestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/contact', function () {
            return response('<html><body><form>'.app(CaptchaManager::class)->driver()->widget()->toHtml().'</form></body></html>');
        });

    }

    public function test_a_page_with_a_captcha_question_is_never_kept(): void
    {
        config(['atrium.cache.pages.enabled' => true, 'atrium.captcha.driver' => 'image']);

        $first = $this->get('/contact')->assertOk()->assertHeaderMissing(CachePages::HEADER)->getContent();
        $second = $this->get('/contact')->assertOk()->assertHeaderMissing(CachePages::HEADER)->getContent();

        // Each visitor gets a question of their own.
        $this->assertNotSame($first, $second);
    }

    public function test_a_page_with_the_captcha_off_is_kept(): void
    {
        config(['atrium.cache.pages.enabled' => true, 'atrium.captcha.driver' => 'none']);

        $this->get('/contact')->assertOk()->assertHeader(CachePages::HEADER, 'MISS');
        $this->get('/contact')->assertOk()->assertHeader(CachePages::HEADER, 'HIT');
    }

    public function test_monitoring_reads_the_numbers_of_the_finished_request(): void
    {
        [$book] = $this->books(1);
        $cache = $this->app->make(RecordCache::class);
        Book::cached($book->id);
        $this->resetRequest();

        Book::cached($book->id);
        $this->assertSame(['hits' => 1, 'misses' => 0], Hook::apply('atrium.cache.stats', ['hits' => 0, 'misses' => 0]));

        // What terminating() does at the end of the request.
        $cache->reset();

        $this->assertSame(['hits' => 1, 'misses' => 0], Hook::apply('atrium.cache.stats', ['hits' => 0, 'misses' => 0]));
    }

    public function test_module_settings_are_read_without_a_query_and_saving_shows_at_once(): void
    {
        $settings = $this->app->make(Settings::class);
        $settings->save('labels', ['color' => 'red']);
        $this->assertSame(['color' => 'red'], $settings->stored('labels'));

        $this->forgetMemo($settings);
        $this->assertSame(['color' => 'red'], $settings->stored('labels'));
        $this->forgetMemo($settings);
        [$queries, $stored] = $this->countQueries(fn () => $settings->stored('labels'));

        $this->assertSame(['color' => 'red'], $stored);
        $this->assertSame(0, $queries);

        $settings->save('labels', ['color' => 'blue']);
        $this->forgetMemo($settings);

        $this->assertSame(['color' => 'blue'], $settings->stored('labels'));
    }

    /**
     * What a new request starts with: the settings not yet read.
     */
    private function forgetMemo(Settings $settings): void
    {
        (fn () => $this->stored = [])->call($settings);
    }
}
