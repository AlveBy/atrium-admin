<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Tests\Fixtures\PlainHtmlTile;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Tile;
use Atrium\Admin\Tests\Fixtures\WeekTile;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia;
use RuntimeException;

/**
 * The tiles of the start page: registered by key, laid out by the config;
 * a broken one is left out instead of taking the panel down.
 */
final class DashboardTest extends TestCase
{
    /** The layout alone, without the panel's own registered tiles after it. */
    private function withoutRegisteredTiles(): void
    {
        Hook::filter('atrium.ui.gadgets', static fn (): array => [], 100);
    }

    /**
     * @return array<string,string> component by key, in the order shown
     */
    private function shown(): array
    {
        $gadgets = [];

        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk()->assertInertia(function (AssertableInertia $page) use (&$gadgets): void {
            $gadgets = array_column($page->toArray()['props']['gadgets'], 'component', 'key');
        });

        return $gadgets;
    }

    public function test_tiles_come_from_the_config_in_its_order(): void
    {
        $this->withoutRegisteredTiles();
        config(['atrium.dashboard.gadgets' => [
            Tile::class,
            ['gadget' => Tile::class, 'title' => 'Half', 'width' => 'half'],
        ]]);

        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Dashboard')
            ->where('gadgets', [
                ['key' => 'gadget-0', 'component' => 'TileGadget', 'title' => 'Tile', 'width' => 'full', 'data' => ['options' => []]],
                ['key' => 'gadget-1', 'component' => 'TileGadget', 'title' => 'Half', 'width' => 'half', 'data' => ['options' => ['title' => 'Half', 'width' => 'half']]],
            ]));
    }

    public function test_an_empty_config_gives_an_empty_dashboard(): void
    {
        $this->withoutRegisteredTiles();
        config(['atrium.dashboard.gadgets' => null]);

        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('gadgets', []));
    }

    public function test_entries_that_are_not_tiles_or_are_switched_off_are_skipped(): void
    {
        $this->withoutRegisteredTiles();
        Exceptions::fake();
        config(['atrium.dashboard.gadgets' => [
            Post::class,
            'No\\Such\\Gadget',
            42,
            ['title' => 'No class'],
            ['gadget' => Tile::class, 'enabled' => false],
            ['gadget' => Tile::class, 'title' => 'Shown'],
        ]]);

        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('gadgets', 1)
            ->where('gadgets.0.key', 'gadget-5')
            ->where('gadgets.0.title', 'Shown'));

        Exceptions::assertNothingReported();
    }

    public function test_a_tile_that_fails_is_reported_and_left_out(): void
    {
        $this->withoutRegisteredTiles();
        Exceptions::fake();
        config(['atrium.dashboard.gadgets' => [
            ['gadget' => Tile::class, 'broken_config' => true],
            ['gadget' => Tile::class, 'broken_data' => true],
            ['gadget' => Tile::class, 'title' => 'Survivor'],
        ]]);

        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('gadgets', 1)
            ->where('gadgets.0.key', 'gadget-2')
            ->where('gadgets.0.title', 'Survivor'));

        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Tile cannot be built.');
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Tile data is broken.');
        Exceptions::assertReportedCount(2);
    }

    public function test_guests_do_not_see_the_dashboard(): void
    {
        config(['atrium.dashboard.gadgets' => [Tile::class]]);

        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_the_panel_registers_its_own_tiles_and_lays_out_two_of_them(): void
    {
        $this->assertSame(['site-speed', 'performance', 'recent'], array_keys(Hook::registered('atrium.ui.gadgets')));
        $this->assertSame(['site-speed', 'performance'], config('atrium.dashboard.gadgets'));
        // "Recent items" has no model to show until the layout names one.
        $this->assertSame(['gadget-0' => 'SiteSpeedGadget', 'gadget-1' => 'PerformanceGadget'], $this->shown());
    }

    public function test_registered_tiles_the_layout_does_not_name_come_after_it(): void
    {
        $this->app['view']->addNamespace('acme', dirname(__DIR__).'/Fixtures/views');
        Hook::register('atrium.ui.gadgets', 'acme.week', WeekTile::class);
        Hook::register('atrium.ui.gadgets', 'acme.tile', Tile::class);
        config(['atrium.dashboard.gadgets' => ['performance', ['gadget' => Tile::class, 'title' => 'Named by class']]]);

        $this->assertSame([
            'gadget-0' => 'PerformanceGadget',
            'gadget-1' => 'TileGadget',
            'gadget-site-speed' => 'SiteSpeedGadget',
            'gadget-acme.week' => 'HtmlGadget',
        ], $this->shown());
    }

    public function test_a_registered_tile_takes_its_options_and_is_switched_off_by_its_key(): void
    {
        Hook::register('atrium.ui.gadgets', 'acme.tile', Tile::class);
        config(['atrium.dashboard.gadgets' => [
            ['gadget' => 'site-speed', 'enabled' => false],
            ['gadget' => 'performance', 'enabled' => false],
            ['gadget' => 'acme.tile', 'title' => 'By key', 'width' => 'half'],
        ]]);

        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('gadgets', [
                ['key' => 'gadget-2', 'component' => 'TileGadget', 'title' => 'By key', 'width' => 'half', 'data' => ['options' => ['title' => 'By key', 'width' => 'half']]],
            ]));
    }

    public function test_what_is_registered_but_is_not_a_tile_class_is_skipped(): void
    {
        Exceptions::fake();
        Hook::register('atrium.ui.gadgets', 'acme.model', Post::class);
        Hook::register('atrium.ui.gadgets', 'acme.number', 42);
        Hook::register('atrium.ui.gadgets', 'acme.object', new Tile);
        config(['atrium.dashboard.gadgets' => ['acme.model', 'acme.object', ['gadget' => 'site-speed', 'enabled' => false], ['gadget' => 'performance', 'enabled' => false]]]);

        $this->assertSame([], $this->shown());
        Exceptions::assertNothingReported();
    }

    public function test_an_html_tile_shows_its_view_with_what_it_prints_escaped(): void
    {
        $this->withoutRegisteredTiles();
        $this->app['view']->addNamespace('acme', dirname(__DIR__).'/Fixtures/views');
        config(['atrium.dashboard.gadgets' => [
            ['gadget' => WeekTile::class, 'note' => 'Friday & after', 'width' => 'half'],
            WeekTile::class,
            PlainHtmlTile::class,
        ]]);

        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('gadgets', [
                ['key' => 'gadget-0', 'component' => 'HtmlGadget', 'title' => 'Time this week', 'width' => 'half', 'data' => ['html' => "<p>&lt;b&gt;12&lt;/b&gt; h, Friday &amp; after</p>\n"]],
                ['key' => 'gadget-1', 'component' => 'HtmlGadget', 'title' => 'Time this week', 'width' => 'full', 'data' => ['html' => "<p>&lt;b&gt;12&lt;/b&gt; h, no note</p>\n"]],
                ['key' => 'gadget-2', 'component' => 'HtmlGadget', 'title' => 'Plain', 'width' => 'full', 'data' => ['html' => "<p>0 options</p>\n"]],
            ]));
    }
}
