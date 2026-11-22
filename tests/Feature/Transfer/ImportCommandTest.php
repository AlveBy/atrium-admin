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
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Transfer\NoExportShelf;
use Atrium\Admin\Tests\Fixtures\Transfer\NoImportShelf;
use Atrium\Admin\Transfer\ImportOption;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * `atrium:import`: a file or a URL into any module, from the console.
 */
final class ImportCommandTest extends TransferTestCase
{
    private const CSV = "Article;Name;Price\nA-1;Parka;1299\nA-2;Coat;99\nA-3;Hat;5\n";

    private string $sources;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Parcel::class, Post::class, NoImportShelf::class, NoExportShelf::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->sources = sys_get_temp_dir().'/atrium-sources-'.Str::random(8);
        File::ensureDirectoryExists($this->sources);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sources);

        parent::tearDown();
    }

    private function file(string $content = self::CSV, string $name = 'prices.csv'): string
    {
        file_put_contents($this->sources.'/'.$name, $content);

        return $this->sources.'/'.$name;
    }

    /**
     * @return array<int,string> what is left in the import directory
     */
    private function leftovers(): array
    {
        return File::isDirectory(DataImport::directory()) ? array_map('basename', File::files(DataImport::directory())) : [];
    }

    public function test_a_file_is_imported_and_reported_and_nothing_is_left_behind(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file()])
            ->expectsOutput('Rows: 3. Created: 3, updated: 0, skipped: 0, errors: 0, tree items created: 0.')
            ->assertExitCode(0);

        $this->assertSame(['A-1' => 'Parka', 'A-2' => 'Coat', 'A-3' => 'Hat'], Parcel::query()->orderBy('id')->pluck('name', 'article')->all());
        $import = DataImport::query()->sole();
        $this->assertSame(['parcels', DataImport::DONE, 'prices.csv', 3, 3, 3], [$import->module, $import->status, $import->original_name, $import->total, $import->processed, $import->created]);
        $this->assertSame(['mapping' => ['article', 'name', 'price'], 'options' => ['key' => 'article', 'mode' => 'both', 'separator' => '/']], ['mapping' => $import->mapping, 'options' => $import->options]);
        $this->assertSame([], $this->leftovers());
    }

    public function test_a_check_writes_nothing_and_says_what_would_happen(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file(), '--check' => true])
            ->expectsOutput('Rows: 3. Created: 3, updated: 0, skipped: 0, errors: 0, tree items created: 0.')
            ->assertExitCode(0);

        $this->assertSame(0, Parcel::query()->count());
        $this->assertSame(DataImport::CHECKED, DataImport::query()->sole()->status);
        $this->assertSame([], $this->leftovers());
    }

    public function test_the_mode_and_the_key_are_taken_from_the_options(): void
    {
        Parcel::query()->create(['article' => 'A-1', 'code' => 'old', 'name' => 'Old name']);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Code;Name\nold;New name\nfresh;Fresh\n"), '--key' => 'code', '--mode' => 'update'])
            ->expectsOutput('Rows: 2. Created: 0, updated: 1, skipped: 1, errors: 0, tree items created: 0.')
            ->assertExitCode(0);

        $this->assertSame(['New name'], Parcel::query()->pluck('name')->all());
        $this->assertSame(['key' => 'code', 'mode' => 'update', 'separator' => '/'], DataImport::query()->sole()->options);
    }

    public function test_the_key_is_found_from_the_columns_when_it_is_not_given(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Code;Name\nc-1;One\n")])->assertExitCode(0);

        $this->assertSame('code', DataImport::query()->sole()->options['key']);
    }

    // --map

    public function test_a_column_is_mapped_to_a_field_by_name_in_any_letters_and_one_can_be_left_out(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Art;Title;Cost\nA-1;Parka;1299\n"), '--map' => ['art=article', 'TITLE=name', 'Cost=']])
            ->assertExitCode(0);

        $this->assertSame([['A-1', 'Parka', null]], Parcel::query()->get()->map(fn (Parcel $parcel): array => [$parcel->article, $parcel->name, $parcel->price])->all());
        $this->assertSame(['article', 'name', null], DataImport::query()->sole()->mapping);
    }

    public function test_a_map_may_name_a_column_that_has_an_equals_sign_in_it(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Article;A=B\nA-1;Parka\n"), '--map' => ['A=B=name']])->assertExitCode(0);

        $this->assertSame(['article', 'name'], DataImport::query()->sole()->mapping);
    }

    public function test_a_map_of_a_column_that_is_not_there_is_refused(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file(), '--map' => ['Nothing=name']])
            ->expectsOutput('No such column for --map: "Nothing=name". Write it as "Column=field".')
            ->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    public function test_a_map_without_an_equals_sign_is_refused(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file(), '--map' => ['Name']])
            ->expectsOutput('No such column for --map: "Name". Write it as "Column=field".')
            ->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    public function test_a_map_to_a_field_that_does_not_exist_is_refused(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file(), '--map' => ['Name=nothing']])->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    public function test_a_field_given_two_columns_is_refused(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file(), '--map' => ['Price=name']])
            ->expectsOutput('A field takes one column only; chosen more than once: Name.')
            ->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    // --like

    public function test_a_file_is_mapped_and_set_up_as_an_earlier_import_was_by_the_names_of_the_columns(): void
    {
        $earlier = $this->importOf('parcels', $this->file("Cost;Title;Extra;Sku\n1;A;x;s\n"), ['key' => 'code', 'mode' => 'create'], ['price', 'name', null, 'code']);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("sku;extra;title;COST;New\ns-1;x;Parka;12;n\n", 'again.csv'), '--like' => (string) $earlier->id])
            ->expectsOutput('Rows: 1. Created: 1, updated: 0, skipped: 0, errors: 0, tree items created: 0.')
            ->assertExitCode(0);

        $import = DataImport::query()->latest('id')->first();
        $this->assertSame(['code', null, 'name', 'price', null], $import->mapping);
        $this->assertSame(['key' => 'code', 'mode' => 'create', 'separator' => '/'], $import->options);
        $this->assertSame([['Parka', '12.00']], Parcel::query()->get()->map(fn (Parcel $parcel): array => [$parcel->name, $parcel->price])->all());
    }

    public function test_what_is_given_beats_the_earlier_import(): void
    {
        $earlier = $this->importOf('parcels', $this->file("Article;Name\nA;B\n"), ['key' => 'article', 'mode' => 'create'], ['article', 'name']);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Article;Name\nA-1;B\n", 'again.csv'), '--like' => (string) $earlier->id, '--mode' => 'update', '--map' => ['Name=']])
            ->assertExitCode(0);

        $import = DataImport::query()->latest('id')->first();
        $this->assertSame(['article', null], $import->mapping);
        $this->assertSame('update', $import->options['mode']);
    }

    public function test_an_import_that_is_not_there_or_of_another_module_cannot_be_followed(): void
    {
        $other = $this->importOf('posts', $this->file("Name\nA\n"));
        $count = DataImport::query()->count();

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file(), '--like' => '999'])
            ->expectsOutput('There is no import number 999 for this module.')->assertExitCode(1);
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file(), '--like' => (string) $other->id])
            ->expectsOutput("There is no import number {$other->id} for this module.")->assertExitCode(1);

        $this->assertSame($count, DataImport::query()->count());
        $this->assertSame(0, Parcel::query()->count());
    }

    // --option

    public function test_an_option_of_the_module_is_given_as_name_and_value(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file(), '--option' => ['separator=>']])->assertExitCode(0);

        $this->assertSame('>', DataImport::query()->sole()->options['separator']);
    }

    public function test_an_option_without_a_value_is_switched_on(): void
    {
        Hook::filter('atrium.import.options', static fn (array $options): array => [...$options, new ImportOption('trim', 'Trim names', ImportOption::BOOLEAN, false)]);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file(), '--option' => ['trim']])->assertExitCode(0);

        $this->assertTrue(DataImport::query()->sole()->options['trim']);
    }

    public function test_an_option_the_module_does_not_have_is_refused(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file(), '--option' => ['nothing=1']])
            ->expectsOutput('The module has no option "nothing" for --option.')
            ->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    // What is refused before anything starts

    public function test_a_module_has_to_be_named_and_to_take_imports(): void
    {
        $listed = 'parcels, posts, no_export_shelves';

        $this->artisan('atrium:import')->expectsOutput("No module \"\" takes imports. The ones that do: {$listed}.")->assertExitCode(1);
        $this->artisan('atrium:import', ['module' => 'nothing', 'source' => $this->file()])->expectsOutput("No module \"nothing\" takes imports. The ones that do: {$listed}.")->assertExitCode(1);
        $this->artisan('atrium:import', ['module' => 'no_import_shelves', 'source' => $this->file()])->expectsOutput("No module \"no_import_shelves\" takes imports. The ones that do: {$listed}.")->assertExitCode(1);
        $this->artisan('atrium:import', ['module' => 'sites', 'source' => $this->file()])->expectsOutput("No module \"sites\" takes imports. The ones that do: {$listed}.")->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    public function test_a_module_that_opts_out_of_the_export_only_still_takes_imports(): void
    {
        $this->artisan('atrium:import', ['module' => 'no_export_shelves', 'source' => $this->file("ID;Name\n1;A\n"), '--check' => true])->assertExitCode(0);

        $this->assertSame('no_export_shelves', DataImport::query()->sole()->module);
    }

    public function test_a_file_or_a_url_has_to_be_named(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels'])->expectsOutput('Name the file or its URL.')->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    public function test_a_file_that_is_not_there_is_refused(): void
    {
        $missing = $this->sources.'/nothing.csv';

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $missing])->expectsOutput("There is no file {$missing}.")->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    /**
     * @return array<string,array{string,string}> content, the message
     */
    public static function unreadable(): array
    {
        return [
            'an empty file' => ['', 'The file has no rows.'],
            'an old Excel file' => ["\xD0\xCF\x11\xE0\x00\x00\x00\x00", 'This is not a CSV or Excel (xlsx) file. An old .xls file can be saved as .xlsx or CSV in Excel.'],
        ];
    }

    #[DataProvider('unreadable')]
    public function test_a_file_that_cannot_be_read_is_refused_and_leaves_nothing(string $content, string $message): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file($content, 'bad.csv')])->expectsOutput($message)->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    // A URL

    public function test_a_url_is_downloaded_and_named_by_its_file_without_the_query(): void
    {
        Http::fake(['https://example.com/*' => Http::response(self::CSV)]);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => 'https://example.com/lists/price%20list.csv?token=1#top'])
            ->expectsOutput('Rows: 3. Created: 3, updated: 0, skipped: 0, errors: 0, tree items created: 0.')
            ->assertExitCode(0);

        $this->assertSame('price list.csv', DataImport::query()->sole()->original_name);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://example.com/lists/price%20list.csv?token=1#top' && $request->method() === 'GET');
        $this->assertSame(3, Parcel::query()->count());
    }

    public function test_the_scheme_of_a_url_may_be_in_capitals_or_plain_http(): void
    {
        Http::fake(['*' => Http::response(self::CSV)]);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => 'HTTP://example.com/a.csv', '--check' => true])->assertExitCode(0);

        Http::assertSentCount(1);
    }

    public function test_a_name_that_is_too_long_is_cut_to_what_the_column_holds(): void
    {
        Http::fake(['*' => Http::response(self::CSV)]);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => 'https://example.com/'.str_repeat('я', 300).'.csv', '--check' => true])->assertExitCode(0);

        $this->assertSame(str_repeat('я', 255), DataImport::query()->sole()->original_name);
    }

    public function test_a_url_that_answers_with_an_error_is_refused(): void
    {
        Http::fake(['*' => Http::response('Not here', 404)]);

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => 'https://example.com/a.csv'])
            ->expectsOutput('The file could not be downloaded from https://example.com/a.csv.')
            ->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    public function test_a_url_that_does_not_answer_is_refused(): void
    {
        Http::fake(static fn () => throw new ConnectionException('Timed out'));

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => 'https://example.com/a.csv'])
            ->expectsOutput('The file could not be downloaded from https://example.com/a.csv.')
            ->assertExitCode(1);

        $this->assertNothingWasStarted();
    }

    public function test_the_downloaded_copy_is_not_left_in_the_temp_directory(): void
    {
        Http::fake(['*' => Http::response(self::CSV)]);
        $before = glob(sys_get_temp_dir().'/atrium-import[!-]*') ?: [];

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => 'https://example.com/a.csv', '--check' => true])->assertExitCode(0);

        $this->assertSame($before, glob(sys_get_temp_dir().'/atrium-import[!-]*') ?: []);
    }

    public function test_a_file_whose_name_looks_like_a_url_only_in_the_middle_is_a_file(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->sources.'/http://nothing.csv'])->assertExitCode(1);

        Http::assertNothingSent();
    }

    // What the report says

    public function test_rows_that_failed_are_listed_and_the_exit_code_says_so(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Article;Name\nA-1;Parka\nA-2;\nA-3;Hat\n")])
            ->expectsOutput('Rows: 3. Created: 2, updated: 0, skipped: 0, errors: 1, tree items created: 0.')
            ->expectsOutputToContain('Row 3: ')
            ->assertExitCode(1);

        $this->assertSame(['A-1', 'A-3'], Parcel::query()->orderBy('id')->pluck('article')->all());
        $this->assertSame(DataImport::DONE, DataImport::query()->sole()->status);
    }

    public function test_skipped_rows_do_not_fail_the_command(): void
    {
        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file("Article;Name\nA-1;Parka\n"), '--mode' => 'update'])
            ->expectsOutput('Rows: 1. Created: 0, updated: 0, skipped: 1, errors: 0, tree items created: 0.')
            ->assertExitCode(0);
    }

    public function test_an_import_that_crashed_says_so_after_the_report_and_fails(): void
    {
        Parcel::creating(static function (): never {
            throw new RuntimeException('Disk on fire');
        });

        $this->artisan('atrium:import', ['module' => 'parcels', 'source' => $this->file()])
            ->expectsOutputToContain('The import stopped because of an error on the server.')
            ->assertExitCode(1);

        $this->assertSame(DataImport::FAILED, DataImport::query()->sole()->status);
        $this->assertSame([], $this->leftovers());
    }

    // --pending

    public function test_the_imports_started_in_the_panel_are_moved_on_and_the_others_are_left_alone(): void
    {
        $checking = $this->importOf('parcels', $this->file("Article;Name\nA-1;One\n"), ['key' => 'article']);
        $checking->begin(DataImport::CHECKING);
        $importing = $this->importOf('parcels', $this->file("Article;Name\nA-2;Two\n", 'two.csv'), ['key' => 'article']);
        $importing->begin(DataImport::IMPORTING);
        $waiting = $this->importOf('parcels', $this->file("Article;Name\nA-3;Three\n", 'three.csv'), ['key' => 'article']);
        $checked = $this->importOf('parcels', $this->file("Article;Name\nA-4;Four\n", 'four.csv'), ['key' => 'article']);
        $checked->forceFill(['status' => DataImport::CHECKED])->save();

        $this->artisan('atrium:import', ['--pending' => true])->doesntExpectOutput('Name the file or its URL.')->assertExitCode(0);

        $this->assertSame(DataImport::CHECKED, $checking->refresh()->status);
        $this->assertSame(DataImport::DONE, $importing->refresh()->status);
        $this->assertSame(DataImport::NEW, $waiting->refresh()->status);
        $this->assertSame(DataImport::CHECKED, $checked->refresh()->status);
        $this->assertSame(['A-2'], Parcel::query()->pluck('article')->all());
    }

    public function test_with_nothing_pending_nothing_happens(): void
    {
        $this->artisan('atrium:import', ['--pending' => true])->assertExitCode(0);

        $this->assertSame(0, DataImport::query()->count());
    }

    public function test_pending_does_not_ask_for_a_module(): void
    {
        $this->artisan('atrium:import', ['--pending' => true, 'module' => 'nothing'])->assertExitCode(0);

        $this->assertSame(0, DataImport::query()->count());
    }

    private function assertNothingWasStarted(): void
    {
        $this->assertSame(0, DataImport::query()->count());
        $this->assertSame([], $this->leftovers());
        $this->assertSame(0, Parcel::query()->count());
    }
}
