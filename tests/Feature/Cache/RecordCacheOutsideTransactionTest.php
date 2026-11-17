<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;

/**
 * A save with no transaction around it: every other test of the cache runs
 * inside the one the test framework opens, this one steps out of it. It
 * saves nothing, so there is nothing left behind.
 */
final class RecordCacheOutsideTransactionTest extends CacheTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        // What an application has: the framework's own manager for tests
        // runs the callbacks of the first transaction at once.
        DB::connection()->setTransactionManager(new DatabaseTransactionsManager);

        $sites = new Collection([(new Site)->setRawAttributes(['id' => null], true)]);
        (new ReflectionProperty(Sites::class, 'all'))->setValue($this->app->make(Sites::class), $sites);
    }

    private function book(): Book
    {
        $book = new Book;
        $book->setAttribute('id', 7);

        return $book;
    }

    public function test_a_save_with_no_transaction_drops_the_cards_once(): void
    {
        [$cache, $store] = $this->spyCache();
        $this->assertSame(0, DB::transactionLevel());

        $cache->changed($this->book());

        // The main site in the language of the application: one key.
        $this->assertCount(1, $store->callsTo('forget'));
    }

    public function test_a_save_in_a_transaction_drops_the_cards_again_once_it_is_committed(): void
    {
        [$cache, $store] = $this->spyCache();

        DB::transaction(function () use ($cache, $store): void {
            $cache->changed($this->book());

            $this->assertCount(1, $store->callsTo('forget'));
        });

        $this->assertCount(2, $store->callsTo('forget'));
    }

    public function test_a_save_in_a_transaction_that_is_rolled_back_drops_the_cards_once(): void
    {
        [$cache, $store] = $this->spyCache();

        DB::beginTransaction();
        $cache->changed($this->book());
        DB::rollBack();

        $this->assertCount(1, $store->callsTo('forget'));
    }
}
