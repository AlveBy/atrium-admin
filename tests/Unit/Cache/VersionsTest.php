<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Cache;

use Atrium\Admin\Cache\Versions;
use Atrium\Admin\Tests\Fixtures\Cache\SpyRepository;
use PHPUnit\Framework\TestCase;

/**
 * The version counters keys are made of, on a store that writes down what it
 * is asked.
 */
final class VersionsTest extends TestCase
{
    private const MONTH = 2592000;

    private SpyRepository $store;

    private Versions $versions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new SpyRepository;
        $this->versions = new Versions($this->store);
    }

    public function test_a_missing_counter_starts_at_a_random_number_that_lives_a_month(): void
    {
        $read = $this->versions->get(['list:Book']);

        $this->assertSame(['list:Book'], array_keys($read));
        $this->assertMatchesRegularExpression('/^[1-9][0-9]*$/', $read['list:Book']);
        $this->assertLessThanOrEqual(PHP_INT_MAX >> 1, (int) $read['list:Book']);

        $added = $this->store->callsTo('add');
        $this->assertCount(1, $added);
        $this->assertSame('atrium:v:list:Book', $added[0][0]);
        $this->assertSame((int) $read['list:Book'], $added[0][1]);
        $this->assertSame(self::MONTH, $added[0][2]);
        // What the store holds is what was handed out.
        $this->assertSame($read['list:Book'], (string) $this->store->get('atrium:v:list:Book'));
    }

    public function test_every_missing_counter_starts_somewhere_of_its_own(): void
    {
        $read = $this->versions->get(['a', 'b']);

        $this->assertNotSame($read['a'], $read['b']);
    }

    public function test_a_counter_the_store_holds_is_read_as_a_string_and_not_started_again(): void
    {
        $this->seed('a', 17);

        $this->assertSame(['a' => '17'], $this->versions->get(['a']));
        $this->assertSame([], $this->store->callsTo('add'));
    }

    public function test_a_counter_the_store_says_false_for_is_started_like_a_missing_one(): void
    {
        $this->store->memcached = true;

        $read = $this->versions->get(['a']);

        $this->assertMatchesRegularExpression('/^[1-9][0-9]*$/', $read['a']);
        $this->assertCount(1, $this->store->callsTo('add'));
    }

    public function test_a_counter_another_process_started_first_is_the_one_used(): void
    {
        $this->store->racer = 777;

        $this->assertSame(['a' => '777'], $this->versions->get(['a']));
    }

    public function test_names_are_read_in_one_multi_get_and_a_name_asked_twice_is_read_once(): void
    {
        $this->seed('a', 1);
        $this->seed('b', 2);

        $this->versions->get(['a', 'b', 'a']);

        $this->assertSame([[['atrium:v:a', 'atrium:v:b']]], $this->store->callsTo('many'));
    }

    public function test_what_was_read_is_kept_in_memory_and_only_new_names_go_to_the_store(): void
    {
        $this->seed('a', 1);
        $this->seed('b', 2);
        $this->versions->get(['a']);

        $this->versions->get(['a']);
        $this->assertCount(1, $this->store->callsTo('many'));

        $this->versions->get(['a', 'b']);
        $this->assertSame([['atrium:v:a'], ['atrium:v:b']], array_map(static fn (array $call): array => $call[0], $this->store->callsTo('many')));
    }

    public function test_only_the_names_asked_for_come_back(): void
    {
        $this->seed('a', 1);
        $this->seed('b', 2);
        $this->versions->get(['a', 'b']);

        $this->assertSame(['b' => '2'], $this->versions->get(['b']));
        $this->assertSame([], $this->versions->get([]));
    }

    public function test_a_stamp_is_one_short_string_that_does_not_depend_on_the_order_of_names(): void
    {
        $this->seed('a', 11);
        $this->seed('b', 22);
        $expected = substr(hash('xxh128', 'a=11|b=22'), 0, 16);

        $this->assertSame($expected, $this->versions->stamp(['a', 'b']));
        $this->assertSame($expected, $this->versions->stamp(['b', 'a']));
        $this->assertSame(16, strlen($expected));
    }

    public function test_a_stamp_changes_with_a_version_and_with_a_name(): void
    {
        $this->seed('a', 11);
        $this->seed('b', 11);
        $this->seed('ab', 11);
        $before = $this->versions->stamp(['a', 'b']);

        $this->assertNotSame($before, $this->versions->stamp(['a']));
        $this->assertNotSame($this->versions->stamp(['a']), $this->versions->stamp(['b']));
        $this->assertNotSame($this->versions->stamp(['ab']), $this->versions->stamp(['a', 'b']));

        $this->versions->bump('a');

        $this->assertNotSame($before, $this->versions->stamp(['a', 'b']));
    }

    public function test_bumping_a_counter_adds_one(): void
    {
        $this->seed('a', 40);

        $this->versions->bump('a');

        $this->assertSame('41', (string) $this->store->get('atrium:v:a'));
        $this->assertSame([['atrium:v:a', 1]], $this->store->callsTo('increment'));
        $this->assertSame([], $this->store->callsTo('put'));

        $this->versions->bump('a');

        $this->assertSame('42', (string) $this->store->get('atrium:v:a'));
    }

    public function test_bumping_a_counter_that_is_at_two_leaves_it_at_three(): void
    {
        $this->seed('a', 2);

        $this->versions->bump('a');

        $this->assertSame('3', (string) $this->store->get('atrium:v:a'));
    }

    public function test_bumping_a_missing_counter_starts_it_at_a_random_number_that_lives_a_month(): void
    {
        $this->versions->bump('a');

        $put = $this->store->callsTo('put');
        $this->assertCount(1, $put);
        $this->assertSame('atrium:v:a', $put[0][0]);
        $this->assertGreaterThan(1, $put[0][1]);
        $this->assertLessThanOrEqual(PHP_INT_MAX >> 1, $put[0][1]);
        $this->assertSame(self::MONTH, $put[0][2]);
        $this->assertSame((string) $put[0][1], $this->versions->get(['a'])['a']);
    }

    public function test_bumping_a_counter_the_store_cannot_increment_starts_it_too(): void
    {
        $this->store->memcached = true;

        $this->versions->bump('a');

        $this->assertCount(1, $this->store->callsTo('put'));
        $this->assertGreaterThan(1, (int) $this->versions->get(['a'])['a']);
    }

    public function test_bumping_a_counter_that_would_start_at_one_starts_it_again_at_a_random_number(): void
    {
        // The array store creates a missing counter at 1: nobody ever read that.
        $this->versions->bump('a');
        $this->versions->bump('b');

        $this->assertNotSame('1', $this->versions->get(['a'])['a']);
        $this->assertNotSame($this->versions->get(['a'])['a'], $this->versions->get(['b'])['b']);
    }

    public function test_bumping_drops_what_was_read_and_only_that(): void
    {
        $this->seed('a', 40);
        $this->seed('b', 50);
        $this->versions->get(['a', 'b']);
        $reads = count($this->store->callsTo('many'));

        $this->versions->bump('a');

        $this->assertEquals(['a' => '41', 'b' => '50'], $this->versions->get(['a', 'b']));
        // b stayed in memory, a was read again.
        $this->assertSame([['atrium:v:a']], array_map(static fn (array $call): array => $call[0], array_slice($this->store->callsTo('many'), $reads)));
    }

    public function test_bumping_a_name_twice_in_one_call_counts_once(): void
    {
        $this->seed('a', 40);
        $this->seed('b', 50);

        $this->versions->bump('a', 'a', 'b');

        $this->assertEquals(['a' => '41', 'b' => '51'], $this->versions->get(['a', 'b']));
        $this->assertCount(2, $this->store->callsTo('increment'));
    }

    public function test_resetting_makes_the_next_read_see_what_another_process_bumped(): void
    {
        $this->seed('a', 40);
        $this->versions->get(['a']);
        $this->seed('a', 99);

        $this->assertSame(['a' => '40'], $this->versions->get(['a']));

        $this->versions->reset();

        $this->assertSame(['a' => '99'], $this->versions->get(['a']));
    }

    private function seed(string $name, int $value): void
    {
        $this->store->put('atrium:v:'.$name, $value, self::MONTH);
        $this->store->calls = [];
    }
}
