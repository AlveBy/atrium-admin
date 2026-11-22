<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Transfer\ImportOption;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * What `atrium:import` does with what it is given: the key among several
 * that the file has, options that hold an equals sign or come several at a
 * time, a path that only looks like a URL, the time a download may take.
 */
final class ImportCommandInputsTest extends TransferTestCase
{
    private string $sources;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sources = sys_get_temp_dir().'/atrium-inputs-'.Str::random(8);
        File::ensureDirectoryExists($this->sources);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sources);

        parent::tearDown();
    }

    private function file(string $content, string $name = 'prices.csv'): string
    {
        File::ensureDirectoryExists(dirname($this->sources.'/'.$name));
        file_put_contents($this->sources.'/'.$name, $content);

        return $this->sources.'/'.$name;
    }

    public function test_the_key_that_is_given_beats_the_first_key_the_file_has(): void
    {
        Parcel::query()->create(['article' => 'A-1', 'code' => 'old', 'name' => 'Old name']);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Article;Code;Name\nA-9;old;New name\n"), '--key' => 'code', '--mode' => 'update'])
            ->expectsOutput('Rows: 1. Created: 0, updated: 1, skipped: 0, errors: 0, tree items created: 0.')
            ->assertExitCode(0);

        $this->assertSame('code', DataImport::query()->sole()->options['key']);
        $this->assertSame([['A-9', 'New name']], Parcel::query()->get()->map(fn (Parcel $parcel): array => [$parcel->article, $parcel->name])->all());
    }

    public function test_without_a_key_the_first_one_the_file_has_is_used_and_the_mode_is_both(): void
    {
        Parcel::query()->create(['article' => 'A-1', 'code' => 'old', 'name' => 'Old name']);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Article;Code;Name\nA-9;old;New name\n")])
            ->expectsOutput('Rows: 1. Created: 1, updated: 0, skipped: 0, errors: 0, tree items created: 0.')
            ->assertExitCode(0);

        $this->assertSame(['key' => 'article', 'mode' => 'both', 'separator' => '/'], DataImport::query()->sole()->options);
    }

    public function test_the_file_the_import_keeps_is_named_by_a_uuid_and_csv(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Article;Name\nA-1;Parka\n"), '--check' => true])->assertExitCode(0);

        $name = DataImport::query()->sole()->file;

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.csv$/', $name);
        $this->assertTrue(Str::isUuid(substr($name, 0, -4)));
    }

    public function test_the_value_of_an_option_may_have_an_equals_sign_in_it(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Article;Name\nA-1;Parka\n"), '--option' => ['separator=a=b']])->assertExitCode(0);

        $this->assertSame('a=b', DataImport::query()->sole()->options['separator']);
    }

    public function test_an_option_with_an_empty_value_is_empty(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Article;Name\nA-1;Parka\n"), '--option' => ['separator=']])->assertExitCode(0);

        $this->assertSame('', DataImport::query()->sole()->options['separator']);
    }

    public function test_every_option_that_is_given_is_taken(): void
    {
        Hook::filter('atrium.import.options', static fn (array $options): array => [...$options, new ImportOption('trim', 'Trim names', ImportOption::BOOLEAN, false), new ImportOption('prefix', 'Prefix', ImportOption::TEXT, '')]);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Article;Name\nA-1;Parka\n"), '--option' => ['separator=>', 'trim', 'prefix=x']])->assertExitCode(0);

        $options = DataImport::query()->sole()->options;
        $this->assertSame(['>', true, 'x'], [$options['separator'], $options['trim'], $options['prefix']]);
    }

    public function test_a_path_with_a_url_in_the_middle_of_it_is_read_as_a_file_when_there_is_one(): void
    {
        Http::fake(['*' => Http::response("Article;Name\nA-1;From the web\n")]);
        $path = $this->file("Article;Name\nA-1;From the disk\n", 'http://files/prices.csv');

        $this->assertStringContainsString('/http://files/prices.csv', $path);
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $path])->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(['From the disk'], Parcel::query()->pluck('name')->all());
    }

    public function test_a_download_may_take_two_minutes(): void
    {
        $timeout = null;
        Http::fake(static function ($request, array $options) use (&$timeout) {
            $timeout = $options['timeout'] ?? null;

            return Http::response("Article;Name\nA-1;Parka\n");
        });

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => 'https://example.com/a.csv', '--check' => true])->assertExitCode(0);

        $this->assertSame(120, $timeout);
    }
}
