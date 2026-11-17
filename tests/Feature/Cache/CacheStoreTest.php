<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\CacheStore;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * The store the record cache writes to: tagged entries where the store has
 * tags, plain keys where it has not, and what it can tell about its size.
 */
final class CacheStoreTest extends TestCase
{
    private string $directory;

    /** @var array<int,array{0:string,1:array<int,mixed>}> */
    private array $asked = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/atrium-cache-store-test-'.getmypid().'-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    private function tagged(): CacheStore
    {
        return new CacheStore(Cache::store('array'), true);
    }

    private function plain(): CacheStore
    {
        return new CacheStore(Cache::store('array'), false);
    }

    private function fileRepository(): CacheRepository
    {
        return new CacheRepository(new FileStore(new Filesystem, $this->directory));
    }

    /**
     * A Redis store whose server answers what the test says.
     *
     * @param  array<string,mixed>  $answers  command => answer, or a Throwable to throw
     */
    private function redis(array $answers): CacheRepository
    {
        $connection = Mockery::mock(RedisConnection::class);
        $connection->shouldReceive('command')->andReturnUsing(function (string $command, array $arguments = []) use ($answers): mixed {
            $this->asked[] = [$command, $arguments];
            $answer = $answers[$command] ?? null;

            if ($answer instanceof RuntimeException) {
                throw $answer;
            }

            return $answer;
        });
        $factory = Mockery::mock(RedisFactory::class);
        $factory->shouldReceive('connection')->andReturn($connection);

        return new CacheRepository(new RedisStore($factory));
    }

    private function databaseRepository(string $table): CacheRepository
    {
        // DDL is not rolled back on MySQL: a table left by an earlier run goes first.
        Schema::dropIfExists($table);
        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->string('key')->primary();
            $blueprint->mediumText('value');
            $blueprint->integer('expiration');
        });

        return new CacheRepository(new DatabaseStore(DB::connection(), $table));
    }

    /**
     * @return array<string,array{0:?string,1:?string,2:bool}>
     */
    public static function strategies(): array
    {
        return [
            'a store with tags, chosen by itself' => ['array', 'auto', true],
            'a store with tags, strategy missing from the config' => ['array', null, true],
            'a store with tags, strategy forced to versions' => ['array', 'versions', false],
            'a store with tags, any other strategy' => ['array', 'tags', true],
            'a store without tags' => ['file', 'auto', false],
            'a store without tags, strategy forced to versions' => ['file', 'versions', false],
        ];
    }

    #[DataProvider('strategies')]
    public function test_tags_are_used_only_where_the_store_has_them_and_the_strategy_allows(?string $store, ?string $strategy, bool $tags): void
    {
        config([
            'cache.stores.filed' => ['driver' => 'file', 'path' => $this->directory],
            'atrium.cache.store' => $store === 'file' ? 'filed' : $store,
        ]);
        $strategy === null ? config()->offsetUnset('atrium.cache.strategy') : config(['atrium.cache.strategy' => $strategy]);

        $this->assertSame($tags, CacheStore::fromConfig()->usesTags());
    }

    public function test_the_store_comes_from_the_config_and_hands_out_its_repository(): void
    {
        config(['atrium.cache.store' => 'array']);

        $store = CacheStore::fromConfig();

        $this->assertSame(Cache::store('array'), $store->repository());
    }

    public function test_a_repository_that_is_not_laravels_never_has_tags(): void
    {
        config(['cache.stores.foreign' => ['driver' => 'foreign'], 'atrium.cache.store' => 'foreign', 'atrium.cache.strategy' => 'auto']);
        Cache::extend('foreign', fn (): Repository => Mockery::mock(Repository::class));

        $store = CacheStore::fromConfig();

        $this->assertFalse($store->usesTags());
        $this->assertSame('custom', $store->driver());
    }

    public function test_the_driver_is_named_after_the_store(): void
    {
        $this->assertSame('array', $this->tagged()->driver());
        $this->assertSame('file', new CacheStore($this->fileRepository(), false)->driver());
        $this->assertSame('redis', new CacheStore($this->redis([]), true)->driver());
        $this->assertSame('database', new CacheStore($this->databaseRepository('driver_cache'), false)->driver());
    }

    public function test_the_size_of_a_store_that_cannot_tell_is_unknown(): void
    {
        $this->assertSame(['entries' => null, 'bytes' => null], $this->tagged()->size());
    }

    public function test_the_size_of_a_repository_that_is_not_laravels_is_unknown(): void
    {
        $store = new CacheStore(Mockery::mock(Repository::class), false);

        $this->assertSame(['entries' => null, 'bytes' => null], $store->size());
    }

    public function test_the_size_of_the_file_store_is_its_files(): void
    {
        $repository = $this->fileRepository();
        $repository->put('one', str_repeat('a', 100), 60);
        $repository->put('two', str_repeat('b', 2000), 60);
        $repository->put('three', 'c', 60);

        $bytes = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $bytes += $file->getSize();
        }

        $size = new CacheStore($repository, false)->size();

        $this->assertSame(['entries' => 3, 'bytes' => $bytes], $size);
        $this->assertGreaterThan(2100, $bytes);
    }

    public function test_the_size_of_an_empty_file_store_is_nothing(): void
    {
        mkdir($this->directory);

        $this->assertSame(['entries' => 0, 'bytes' => 0], new CacheStore($this->fileRepository(), false)->size());
    }

    public function test_a_file_store_without_its_directory_has_no_size_and_no_error(): void
    {
        $this->assertSame(['entries' => null, 'bytes' => null], new CacheStore($this->fileRepository(), false)->size());
    }

    public function test_the_size_of_the_database_store_counts_its_rows_in_the_table_of_the_configured_store(): void
    {
        $repository = $this->databaseRepository('atrium_kept');
        $repository->put('a', 1, 60);
        $repository->put('b', 2, 60);
        config(['cache.stores.kept' => ['driver' => 'database', 'table' => 'atrium_kept'], 'atrium.cache.store' => 'kept', 'cache.default' => 'array']);

        $this->assertSame(['entries' => 2, 'bytes' => null], new CacheStore($repository, false)->size());
    }

    public function test_the_size_of_the_database_store_uses_the_default_store_when_none_is_named(): void
    {
        $repository = $this->databaseRepository('atrium_default_kept');
        $repository->put('a', 1, 60);
        config(['cache.stores.kept' => ['driver' => 'database', 'table' => 'atrium_default_kept'], 'atrium.cache.store' => null, 'cache.default' => 'kept']);

        $this->assertSame(['entries' => 1, 'bytes' => null], new CacheStore($repository, false)->size());
    }

    public function test_the_size_of_the_database_store_falls_back_to_the_table_named_cache(): void
    {
        $repository = $this->databaseRepository('cache');
        $repository->put('a', 1, 60);
        $repository->put('b', 2, 60);
        $repository->put('c', 3, 60);
        config(['cache.stores.kept' => ['driver' => 'database'], 'atrium.cache.store' => 'kept']);

        $this->assertSame(['entries' => 3, 'bytes' => null], new CacheStore($repository, false)->size());
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:?int}>
     */
    public static function memoryAnswers(): array
    {
        return [
            'in sections, as predis answers' => [['Memory' => ['used_memory' => '1048576', 'used_memory_human' => '1M']], 1048576],
            'flat, as phpredis answers' => [['used_memory' => '2048', 'used_memory_peak' => '4096'], 2048],
            'a number already' => [['used_memory' => 512], 512],
            'no such figure' => [['used_memory_human' => '1M'], null],
            'an empty answer' => [[], null],
        ];
    }

    /**
     * @param  array<string,mixed>  $info
     */
    #[DataProvider('memoryAnswers')]
    public function test_the_size_of_the_redis_store_is_its_keys_and_memory(array $info, ?int $bytes): void
    {
        $store = new CacheStore($this->redis(['dbsize' => '42', 'info' => $info]), false);

        $this->assertSame(['entries' => 42, 'bytes' => $bytes], $store->size());
        // The memory section of the server, and nothing else of its report.
        $this->assertSame([['dbsize', []], ['info', ['memory']]], $this->asked);
    }

    public function test_a_redis_that_does_not_answer_has_no_size_and_no_error(): void
    {
        $store = new CacheStore($this->redis(['dbsize' => new RuntimeException('connection refused')]), false);

        $this->assertSame(['entries' => null, 'bytes' => null], $store->size());
    }

    public function test_a_redis_that_stops_halfway_keeps_what_it_told(): void
    {
        $store = new CacheStore($this->redis(['dbsize' => 7, 'info' => new RuntimeException('connection lost')]), false);

        $this->assertSame(['entries' => 7, 'bytes' => null], $store->size());
    }

    public function test_entries_are_written_and_read_back_in_one_go_whatever_the_store(): void
    {
        foreach ([$this->tagged(), $this->plain()] as $store) {
            $store->putMany('shelf', ['a' => 1, 'b' => ['x' => 2]], 60);

            $this->assertSame(['b' => ['x' => 2], 'missing' => null, 'a' => 1], $store->many('shelf', ['b', 'missing', 'a']));
            $this->assertSame(1, $store->get('shelf', 'a'));
            $this->assertNull($store->get('shelf', 'missing'));
        }
    }

    public function test_nothing_asked_for_is_nothing_read(): void
    {
        $repository = Mockery::mock(CacheRepository::class);
        $repository->shouldNotReceive('many');
        $repository->shouldNotReceive('tags');

        $this->assertSame([], new CacheStore($repository, true)->many('shelf', []));
    }

    public function test_nothing_to_write_is_nothing_written(): void
    {
        $repository = Mockery::mock(CacheRepository::class);
        $repository->shouldNotReceive('tags');
        $repository->shouldNotReceive('putMany');

        foreach ([true, false] as $tags) {
            new CacheStore($repository, $tags)->putMany('shelf', [], 60);
        }

        $this->addToAssertionCount(1);
    }

    public function test_a_plain_entry_sits_under_the_atrium_key_of_the_store(): void
    {
        $this->plain()->put('shelf', 'k', 'value', 60);

        $this->assertSame('value', Cache::store('array')->get('atrium:k'));
        $this->assertNull(Cache::store('array')->get('k'));
    }

    public function test_a_plain_entry_with_a_number_for_a_key_is_written(): void
    {
        $this->plain()->putMany('shelf', ['5' => 'five'], 60);

        $this->assertSame('five', Cache::store('array')->get('atrium:5'));
        $this->assertSame(['5' => 'five'], $this->plain()->many('shelf', ['5']));
    }

    public function test_a_tagged_entry_is_the_one_laravel_keeps_under_the_group_and_atrium_tags(): void
    {
        $this->tagged()->put('shelf', 'k', 'value', 60);

        $this->assertSame('value', Cache::store('array')->tags(['atrium', 'atrium:shelf'])->get('k'));
        $this->assertNull(Cache::store('array')->tags(['atrium', 'atrium:other'])->get('k'));
        $this->assertNull(Cache::store('array')->get('atrium:k'));
    }

    public function test_an_entry_lives_for_its_time_and_not_longer(): void
    {
        foreach ([$this->tagged(), $this->plain()] as $store) {
            $store->put('shelf', 'k', 'value', 60);

            $this->travel(59)->seconds();
            $this->assertSame('value', $store->get('shelf', 'k'));

            $this->travel(2)->seconds();
            $this->assertNull($store->get('shelf', 'k'));

            $this->travelBack();
        }
    }

    public function test_entries_are_forgotten_by_key_in_their_group_only(): void
    {
        foreach ([$this->tagged(), $this->plain()] as $store) {
            $store->putMany('shelf', ['a' => 1, 'b' => 2, 'c' => 3], 60);
            $store->put('other', 'a', 4, 60);

            $store->forget('shelf', ['a', 'c']);

            $this->assertSame(['a' => null, 'b' => 2, 'c' => null], $store->many('shelf', ['a', 'b', 'c']));
            $expected = $store->usesTags() ? 4 : null;
            $this->assertSame($expected, $store->get('other', 'a'));
        }
    }

    public function test_flushing_a_group_deletes_its_entries_and_no_others(): void
    {
        $store = $this->tagged();
        $store->put('shelf', 'a', 1, 60);
        $store->put('other', 'a', 2, 60);
        Cache::store('array')->put('outside', 3, 60);

        $store->flush('shelf');

        $this->assertNull($store->get('shelf', 'a'));
        $this->assertSame(2, $store->get('other', 'a'));
        $this->assertSame(3, Cache::store('array')->get('outside'));
    }

    public function test_flushing_everything_deletes_every_group_and_leaves_the_rest_of_the_store(): void
    {
        $store = $this->tagged();
        $store->put('shelf', 'a', 1, 60);
        $store->put('other', 'a', 2, 60);
        Cache::store('array')->put('outside', 3, 60);

        $store->flush();

        $this->assertNull($store->get('shelf', 'a'));
        $this->assertNull($store->get('other', 'a'));
        $this->assertSame(3, Cache::store('array')->get('outside'));
    }

    public function test_a_flushed_group_is_written_and_read_again_under_its_new_tags(): void
    {
        $store = $this->tagged();
        $store->put('shelf', 'a', 1, 60);
        $store->get('shelf', 'a');

        $store->flush('shelf');
        $store->put('shelf', 'a', 2, 60);

        $this->assertSame(2, $store->get('shelf', 'a'));
    }

    public function test_the_tags_of_a_group_are_worked_out_once_until_reset(): void
    {
        $repository = new class(Cache::store('array')->getStore()) extends CacheRepository
        {
            public int $tagged = 0;

            public function tags($names)
            {
                $this->tagged++;

                return parent::tags($names);
            }
        };
        $store = new CacheStore($repository, true);

        $store->many('shelf', ['a', 'b', 'c']);
        $store->get('shelf', 'a');
        $this->assertSame(1, $repository->tagged);

        $store->many('other', ['a']);
        $this->assertSame(2, $repository->tagged);

        $store->reset();
        $store->get('shelf', 'a');
        $this->assertSame(3, $repository->tagged);
    }

    public function test_after_a_reset_the_tags_flushed_by_someone_else_are_taken_anew(): void
    {
        $store = $this->tagged();
        $store->put('shelf', 'a', 1, 60);
        $store->get('shelf', 'a');

        // Another process cleared the module's cache; this one still knows the old tags.
        Cache::store('array')->tags(['atrium:shelf'])->flush();
        $store->put('shelf', 'a', 2, 60);
        $this->assertSame(1, $store->get('shelf', 'a'));

        $store->reset();

        $this->assertSame(2, $store->get('shelf', 'a'));
    }

    public function test_a_store_without_tags_flushes_nothing_and_loses_nothing(): void
    {
        $repository = Mockery::mock(CacheRepository::class);
        $repository->shouldNotReceive('tags');
        $repository->shouldReceive('putMany')->once()->with(['atrium:a' => 1], 60);
        $repository->shouldReceive('many')->andReturn(['atrium:a' => 1]);
        $store = new CacheStore($repository, false);
        $store->put('shelf', 'a', 1, 60);

        $store->flush();
        $store->flush('shelf');

        $this->assertSame(1, $store->get('shelf', 'a'));
    }
}
