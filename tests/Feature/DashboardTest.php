<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Tile;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia;
use RuntimeException;

/**
 * The tiles of the start page, read from the config; a broken one is left
 * out instead of taking the panel down.
 */
final class DashboardTest extends TestCase
{
    public function test_tiles_come_from_the_config_in_its_order(): void
    {
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
        config(['atrium.dashboard.gadgets' => null]);

        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('gadgets', []));
    }

    public function test_entries_that_are_not_tiles_or_are_switched_off_are_skipped(): void
    {
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
}
