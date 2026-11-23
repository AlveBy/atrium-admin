<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Support\ConfigList;
use Atrium\Admin\Tests\TestCase;

final class ConfigListTest extends TestCase
{
    public function test_an_entry_is_added_to_the_end_of_the_list(): void
    {
        config(['atrium-theme.header_views' => ['a::menu']]);

        ConfigList::add('atrium-theme.header_views', 'atrium-search::box');

        $this->assertSame(['a::menu', 'atrium-search::box'], config('atrium-theme.header_views'));
    }

    public function test_an_entry_already_in_a_cached_config_is_not_added_again(): void
    {
        // What `php artisan config:cache` leaves: the entry from the boot that wrote the cache.
        config(['atrium-theme.header_views' => ['atrium-search::box', 'a::menu']]);

        ConfigList::add('atrium-theme.header_views', 'atrium-search::box');
        ConfigList::add('atrium-theme.header_views', 'atrium-search::box');

        $this->assertSame(['atrium-search::box', 'a::menu'], config('atrium-theme.header_views'));
    }

    public function test_a_missing_list_is_started(): void
    {
        config(['atrium.missing' => null]);

        ConfigList::add('atrium.missing', 'One');

        $this->assertSame(['One'], config('atrium.missing'));
    }

    public function test_only_the_same_string_counts_as_already_there(): void
    {
        // A loose == takes '10' and '1e1' for the same number.
        config(['atrium.list' => ['10', 'Search']]);

        ConfigList::add('atrium.list', '1e1');
        ConfigList::add('atrium.list', 'search');

        $this->assertSame(['10', 'Search', '1e1', 'search'], config('atrium.list'));
    }
}
