<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Tests\Fixtures\Bulletin;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Transfer\NoExportShelf;
use Atrium\Admin\Tests\Fixtures\Transfer\NoImportShelf;
use Atrium\Admin\Transfer\ImportFailed;
use Atrium\Admin\Transfer\SourceFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * `atrium:export`: the list of any module as a file, from the console.
 */
final class ExportCommandTest extends TransferTestCase
{
    private string $out;

    private string $cwd;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Parcel::class, Post::class, Bulletin::class, NoExportShelf::class, NoImportShelf::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-22 12:00:00');
        $this->out = sys_get_temp_dir().'/atrium-exported-'.Str::random(8);
        File::ensureDirectoryExists($this->out);
        $this->cwd = (string) getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        File::deleteDirectory($this->out);
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function parcels(): void
    {
        foreach ([['Alpha', 'A-1', 3], ['Bravo', 'A-2', 1], ['Charlie', 'A-3', 2], ['Delta', 'A-4', 1]] as [$name, $article, $sort]) {
            Parcel::query()->create(['name' => $name, 'article' => $article, 'sort' => $sort]);
        }
    }

    /**
     * What a file holds, read back as the import reads a file.
     *
     * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    private function read(string $path): array
    {
        try {
            $read = (new SourceFile)->normalize($path, $this->out.'/read.txt');
        } catch (ImportFailed) {
            return ['headers' => [], 'rows' => []];
        }

        return ['headers' => $read['headers'], 'rows' => array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), file($this->out.'/read.txt', FILE_IGNORE_NEW_LINES))];
    }

    /**
     * @return array<int,string> the article (the third cell) of every row
     */
    private function articles(string $path): array
    {
        return array_column($this->read($path)['rows'], 2);
    }

    public function test_a_file_is_written_where_it_is_told_and_the_count_is_reported(): void
    {
        $this->parcels();
        $path = $this->out.'/list.csv';

        $this->artisan('atrium:export', ['module' => 'parcels', 'file' => $path])->expectsOutput("Records written: 4, to {$path}.")->assertExitCode(0);

        $this->assertStringStartsWith("\xEF\xBB\xBFID;Article;Name;Code;", (string) file_get_contents($path));
        $this->assertSame(['A-4', 'A-3', 'A-2', 'A-1'], $this->articles($path));
    }

    public function test_without_a_name_it_is_the_module_and_the_day_in_csv_in_the_current_directory(): void
    {
        $this->parcels();
        chdir($this->out);

        $this->artisan('atrium:export', ['module' => 'parcels'])->expectsOutput('Records written: 4, to parcels-2026-11-22.csv.')->assertExitCode(0);

        $this->assertFileExists($this->out.'/parcels-2026-11-22.csv');
        $this->assertSame(['A-4', 'A-3', 'A-2', 'A-1'], $this->articles($this->out.'/parcels-2026-11-22.csv'));
    }

    public function test_without_a_name_the_format_is_the_extension_of_the_default_name(): void
    {
        $this->parcels();
        chdir($this->out);

        $this->artisan('atrium:export', ['module' => 'parcels', '--format' => 'xlsx'])->expectsOutput('Records written: 4, to parcels-2026-11-22.xlsx.')->assertExitCode(0);

        $this->assertSame('PK', substr((string) file_get_contents($this->out.'/parcels-2026-11-22.xlsx'), 0, 2));
        $this->assertFileDoesNotExist($this->out.'/parcels-2026-11-22.csv');
    }

    public function test_the_module_is_named_in_the_default_name(): void
    {
        Post::query()->create(['name' => 'Hello']);
        chdir($this->out);

        $this->artisan('atrium:export', ['module' => 'posts'])->expectsOutput('Records written: 1, to posts-2026-11-22.csv.')->assertExitCode(0);

        $this->assertFileExists($this->out.'/posts-2026-11-22.csv');
    }

    /**
     * @return array<string,array{string,string}> name, how it begins
     */
    public static function extensions(): array
    {
        return [
            'csv' => ['a.csv', "\xEF\xBB\xBF"],
            'xlsx' => ['a.xlsx', 'PK'],
            'in capitals' => ['a.XLSX', 'PK'],
            'csv in capitals' => ['A.CSV', "\xEF\xBB\xBF"],
            'in a directory with a dot' => ['b.d/a.xlsx', 'PK'],
        ];
    }

    #[DataProvider('extensions')]
    public function test_the_format_follows_the_extension_of_the_name(string $name, string $beginning): void
    {
        $this->parcels();
        File::ensureDirectoryExists(dirname($this->out.'/'.$name));

        $this->artisan('atrium:export', ['module' => 'parcels', 'file' => $this->out.'/'.$name])->assertExitCode(0);

        $this->assertSame($beginning, substr((string) file_get_contents($this->out.'/'.$name), 0, strlen($beginning)));
    }

    public function test_the_format_option_beats_the_extension(): void
    {
        $this->parcels();

        $this->artisan('atrium:export', ['module' => 'parcels', 'file' => $this->out.'/a.csv', '--format' => 'xlsx'])->assertExitCode(0);
        $this->artisan('atrium:export', ['module' => 'parcels', 'file' => $this->out.'/b.xlsx', '--format' => 'csv'])->assertExitCode(0);
        $this->artisan('atrium:export', ['module' => 'parcels', 'file' => $this->out.'/c.txt', '--format' => 'csv'])->assertExitCode(0);

        $this->assertSame('PK', substr((string) file_get_contents($this->out.'/a.csv'), 0, 2));
        $this->assertSame("\xEF\xBB\xBF", substr((string) file_get_contents($this->out.'/b.xlsx'), 0, 3));
        $this->assertSame("\xEF\xBB\xBF", substr((string) file_get_contents($this->out.'/c.txt'), 0, 3));
    }

    /**
     * @return array<string,array{array<string,string>}>
     */
    public static function unknownFormats(): array
    {
        return [
            'an extension that is no format' => [['file' => 'a.xls']],
            'no extension' => [['file' => 'list']],
            'a format that is none' => [['file' => 'a.csv', '--format' => 'pdf']],
            'a format in capitals' => [['file' => 'a.csv', '--format' => 'CSV']],
            'a format of the default name' => [['--format' => 'pdf']],
        ];
    }

    /**
     * @param  array<string,string>  $arguments
     */
    #[DataProvider('unknownFormats')]
    public function test_a_format_that_is_none_is_refused_and_nothing_is_written(array $arguments): void
    {
        $this->parcels();
        chdir($this->out);
        $arguments = array_map(fn (string $value): string => str_ends_with($value, '.xls') || $value === 'list' || $value === 'a.csv' ? $this->out.'/'.$value : $value, $arguments);

        $this->artisan('atrium:export', ['module' => 'parcels', ...$arguments])->expectsOutput('The format must be one of: csv, xlsx.')->assertExitCode(1);

        $this->assertSame([], array_map('basename', File::files($this->out)));
    }

    public function test_a_module_has_to_be_there_and_to_take_exports(): void
    {
        $listed = 'sites, languages, parcels, posts, bulletins, no_import_shelves';

        $this->artisan('atrium:export', ['module' => 'nothing'])->expectsOutput("No module \"nothing\" can be exported. The ones that can: {$listed}.")->assertExitCode(1);
        $this->artisan('atrium:export', ['module' => 'no_export_shelves'])->expectsOutput("No module \"no_export_shelves\" can be exported. The ones that can: {$listed}.")->assertExitCode(1);
    }

    public function test_the_sites_and_the_languages_can_be_exported_though_they_cannot_be_imported(): void
    {
        $this->artisan('atrium:export', ['module' => 'sites', 'file' => $this->out.'/sites.csv'])->assertExitCode(0);
        $this->artisan('atrium:export', ['module' => 'languages', 'file' => $this->out.'/languages.csv'])->assertExitCode(0);

        $this->assertFileExists($this->out.'/sites.csv');
        $this->assertFileExists($this->out.'/languages.csv');
    }

    public function test_a_module_that_opts_out_of_the_import_only_is_exported(): void
    {
        NoImportShelf::query()->create(['name' => 'A']);

        $this->artisan('atrium:export', ['module' => 'no_import_shelves', 'file' => $this->out.'/a.csv'])->expectsOutput("Records written: 1, to {$this->out}/a.csv.")->assertExitCode(0);
    }

    public function test_the_search_narrows_the_file(): void
    {
        $this->parcels();

        $this->artisan('atrium:export', ['module' => 'parcels', 'file' => $this->out.'/a.csv', '--search' => 'rav'])->expectsOutput("Records written: 1, to {$this->out}/a.csv.")->assertExitCode(0);
        $this->artisan('atrium:export', ['module' => 'parcels', 'file' => $this->out.'/b.csv', '--search' => 'nothing'])->expectsOutput("Records written: 0, to {$this->out}/b.csv.")->assertExitCode(0);

        $this->assertSame(['A-2'], $this->articles($this->out.'/a.csv'));
        $this->assertSame([], $this->articles($this->out.'/b.csv'));
        $this->assertStringStartsWith("\xEF\xBB\xBFID;Article;", (string) file_get_contents($this->out.'/b.csv'));
    }

    public function test_the_sort_and_its_direction_order_the_file(): void
    {
        $this->parcels();

        foreach ([['name', 'asc', ['A-1', 'A-2', 'A-3', 'A-4']], ['name', 'desc', ['A-4', 'A-3', 'A-2', 'A-1']], ['sort', 'asc', ['A-2', 'A-4', 'A-3', 'A-1']], ['sort', 'desc', ['A-1', 'A-3', 'A-2', 'A-4']]] as [$sort, $direction, $expected]) {
            $path = "{$this->out}/{$sort}-{$direction}.csv";
            $this->artisan('atrium:export', ['module' => 'parcels', 'file' => $path, '--sort' => $sort, '--direction' => $direction])->assertExitCode(0);

            $this->assertSame($expected, $this->articles($path), "{$sort} {$direction}");
        }
    }

    public function test_a_filter_of_the_module_narrows_the_file(): void
    {
        foreach ([['Alpha', 1], ['Beta', 0], ['Gamma', 1]] as [$name, $active]) {
            Bulletin::query()->create(['name' => $name, 'active' => $active]);
        }

        $names = function (array $filters): array {
            $path = $this->out.'/'.Str::random(6).'.csv';
            $this->artisan('atrium:export', ['module' => 'bulletins', 'file' => $path, '--filter' => $filters])->assertExitCode(0);

            return $this->articles($path);
        };

        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $names([]));
        $this->assertSame(['Gamma', 'Alpha'], $names(['active=1']));
        $this->assertSame(['Beta'], $names(['active=0']));
        $this->assertSame(['Beta'], $names(['name=Beta']));
        $this->assertSame([], $names(['name=Beta', 'active=1']));
        $this->assertSame(['Gamma', 'Alpha'], $names(['name=', 'active=1']));
        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $names(['active']));
        $this->assertSame([], $names(['name=Beta=1']));
    }

    public function test_search_filters_and_sort_work_together(): void
    {
        foreach ([['Alpha', 1], ['Alphabet', 1], ['Alp', 0], ['Beta', 1]] as [$name, $active]) {
            Bulletin::query()->create(['name' => $name, 'active' => $active]);
        }
        $path = $this->out.'/a.csv';

        $this->artisan('atrium:export', ['module' => 'bulletins', 'file' => $path, '--search' => 'alp', '--filter' => ['active=1'], '--sort' => 'name', '--direction' => 'asc'])->assertExitCode(0);

        $this->assertSame(['Alpha', 'Alphabet'], $this->articles($path));
    }

    public function test_the_whole_list_is_written_whatever_its_size(): void
    {
        foreach (range(1, 30) as $number) {
            Post::query()->create(['name' => "Post {$number}"]);
        }
        $path = $this->out.'/a.csv';

        $this->artisan('atrium:export', ['module' => 'posts', 'file' => $path])->expectsOutput("Records written: 30, to {$path}.")->assertExitCode(0);

        $this->assertCount(30, $this->read($path)['rows']);
    }
}
