<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Http\Controllers\ImportController;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\Concerns\CreatesEditors;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Transfer\NoExportShelf;
use Atrium\Admin\Tests\Fixtures\Transfer\NoImportShelf;
use Atrium\Admin\Transfer\ImportOption;
use Atrium\Admin\Transfer\ImportRunner;
use Atrium\Admin\Transfer\Output\XlsxOutput;
use Atrium\Admin\Transfer\ProcessImport;
use Atrium\Admin\Transfer\SourceFile;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The import screens of a module: upload, map, check, import, report.
 */
final class ImportControllerTest extends TransferTestCase
{
    use CreatesEditors;

    private const CSV = "Article;Name;Price\nA-1;Parka;1299\nA-2;Coat;99\nA-3;Hat;5\n";

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Parcel::class, Post::class, NoImportShelf::class, NoExportShelf::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-22 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string,mixed>
     */
    private function props(TestResponse $response): array
    {
        $props = [];
        $response->assertInertia(function (AssertableInertia $page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    private function upload(string $content = self::CSV, string $name = 'prices.csv', string $module = 'parcels', ?AdminUser $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin(), 'admin')->post("/admin/{$module}/import", ['file' => UploadedFile::fake()->createWithContent($name, $content)]);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function makeImport(array $attributes = [], string $content = self::CSV): DataImport
    {
        File::ensureDirectoryExists($this->directory);
        file_put_contents($this->directory.'/source.csv', $content);
        $path = $this->directory.'/prepared.csv';
        $read = app(SourceFile::class)->normalize($this->directory.'/source.csv', $path);
        $file = Str::uuid().'.csv';
        rename($path, $this->directory.'/'.$file);

        $import = new DataImport;
        $import->forceFill([
            ...$read, 'module' => 'parcels', 'status' => DataImport::NEW, 'original_name' => 'prices.csv', 'file' => $file,
            'mapping' => ['article', 'name', 'price'], 'options' => ['key' => 'article', 'mode' => 'both'], ...$attributes,
        ])->save();

        return $import;
    }

    // The list of imports

    public function test_the_page_lists_the_imports_of_that_module_only_newest_first(): void
    {
        $admin = $this->admin();
        $mine = $this->makeImport(['admin_user_id' => $admin->id]);
        $other = $this->makeImport(['module' => 'posts']);
        $second = $this->makeImport(['admin_user_id' => null]);

        $response = $this->actingAs($admin, 'admin')->get('/admin/parcels/import');

        $response->assertOk();
        $props = $this->props($response);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Transfer/Import/Index'));

        $this->assertSame([$second->id, $mine->id], array_column($props['imports'], 'id'));
        $this->assertNotContains($other->id, array_column($props['imports'], 'id'));
        $this->assertSame(['parcels', 'admin.parcels', 'Parcels', 51200], [$props['module'], $props['routeName'], $props['title'], $props['maxSize']]);
        $this->assertSame([null, 'Admin'], array_column($props['imports'], 'user'));
    }

    public function test_the_page_lists_twenty_imports_at_most(): void
    {
        foreach (range(1, 22) as $number) {
            $this->makeImport(['original_name' => "file-{$number}.csv"]);
        }

        $imports = $this->props($this->actingAs($this->admin(), 'admin')->get('/admin/parcels/import'))['imports'];

        $this->assertCount(20, $imports);
        $this->assertSame('file-22.csv', $imports[0]['original_name']);
        $this->assertSame('file-3.csv', $imports[19]['original_name']);
    }

    public function test_an_import_is_shown_with_its_counters_dates_and_the_options_it_has_filled_in_over_the_defaults(): void
    {
        $admin = $this->admin();
        $import = $this->makeImport(['admin_user_id' => $admin->id]);
        $import->forceFill([
            'status' => DataImport::DONE, 'processed' => 3, 'created' => 2, 'updated' => 1, 'skipped' => 4, 'failed' => 5, 'nodes' => 6, 'message' => 'Said so',
            'errors' => [['row' => 2, 'skipped' => false, 'messages' => ['Bad']]],
            'started_at' => '2026-11-22 10:00:00', 'finished_at' => '2026-11-22 10:05:00', 'options' => ['key' => 'article', 'mode' => 'update'],
        ])->save();

        $state = $this->props($this->actingAs($admin, 'admin')->get('/admin/parcels/import'))['imports'][0];

        $this->assertSame([
            'id' => $import->id,
            'status' => 'done',
            'original_name' => 'prices.csv',
            'format' => 'csv',
            'encoding' => 'UTF-8',
            'delimiter' => ';',
            'headers' => ['Article', 'Name', 'Price'],
            'mapping' => ['article', 'name', 'price'],
            'total' => 3,
            'processed' => 3,
            'created' => 2,
            'updated' => 1,
            'skipped' => 4,
            'failed' => 5,
            'nodes' => 6,
            'errors' => [['row' => 2, 'skipped' => false, 'messages' => ['Bad']]],
            'message' => 'Said so',
            'options' => ['mode' => 'update', 'separator' => '/', 'key' => 'article'],
            'user' => 'Admin',
            'created_at' => '2026-11-22T12:00:00+00:00',
            'started_at' => '2026-11-22T10:00:00+00:00',
            'finished_at' => '2026-11-22T10:05:00+00:00',
        ], $state);
    }

    public function test_a_new_import_has_no_dates_and_the_options_of_the_module_as_they_are_by_default(): void
    {
        $this->makeImport(['module' => 'posts', 'options' => null]);

        $state = $this->props($this->actingAs($this->admin(), 'admin')->get('/admin/posts/import'))['imports'][0];

        $this->assertSame(['mode' => 'both'], $state['options']);
        $this->assertNull($state['started_at']);
        $this->assertNull($state['finished_at']);
        $this->assertNull($state['user']);
        $this->assertSame('new', $state['status']);
    }

    // Upload

    public function test_a_file_becomes_an_import_waiting_for_the_check_with_the_columns_guessed(): void
    {
        $admin = $this->admin();

        $response = $this->upload(as: $admin);

        $import = DataImport::query()->sole();
        $response->assertRedirect("/admin/parcels/import/{$import->id}");
        $this->assertSame('parcels', $import->module);
        $this->assertSame($admin->id, $import->admin_user_id);
        $this->assertSame(DataImport::NEW, $import->status);
        $this->assertSame('prices.csv', $import->original_name);
        $this->assertSame(['csv', 'UTF-8', ';', ['Article', 'Name', 'Price'], 3], [$import->format, $import->encoding, $import->delimiter, $import->headers, $import->total]);
        $this->assertSame(['article', 'name', 'price'], $import->mapping);
        $this->assertSame(['key' => 'article'], $import->options);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.csv$/', $import->file);
        $this->assertFileExists($import->path());
        $this->assertSame("2,A-1,Parka,1299\n3,A-2,Coat,99\n4,A-3,Hat,5\n", file_get_contents($import->path()));
    }

    public function test_an_excel_file_is_taken_too(): void
    {
        File::ensureDirectoryExists($this->directory);
        $path = $this->directory.'/in.xlsx';
        $output = new XlsxOutput($path);
        $output->row(['Article', 'Name']);
        $output->row(['A-1', 'Parka']);
        $output->close();

        $this->actingAs($this->admin(), 'admin')->post('/admin/parcels/import', ['file' => new UploadedFile($path, 'in.xlsx', null, null, true)])->assertRedirect();

        $import = DataImport::query()->sole();
        $this->assertSame(['xlsx', ['Article', 'Name'], 1, 'in.xlsx'], [$import->format, $import->headers, $import->total, $import->original_name]);
    }

    public function test_a_long_name_is_cut_to_what_the_column_holds(): void
    {
        $this->upload(name: str_repeat('я', 300).'.csv');

        $this->assertSame(str_repeat('я', 255), DataImport::query()->sole()->original_name);
    }

    public function test_a_name_of_255_characters_is_kept_whole(): void
    {
        $name = str_repeat('b', 251).'.csv';
        $this->upload(name: $name);

        $this->assertSame($name, DataImport::query()->sole()->original_name);
    }

    public function test_a_file_seen_for_the_first_time_is_found_by_the_first_key_its_columns_have(): void
    {
        $this->upload("Code;Name\nc-1;Parka\n");

        $this->assertSame(['key' => 'code'], DataImport::query()->sole()->options);
    }

    public function test_a_file_with_no_key_column_gets_the_first_key_of_the_module(): void
    {
        $this->upload("Name;Price\nParka;1299\n");

        $this->assertSame(['key' => 'article'], DataImport::query()->sole()->options);
    }

    public function test_the_screen_of_a_new_upload_has_the_key_chosen(): void
    {
        $admin = $this->admin();
        $this->upload(as: $admin);
        $import = DataImport::query()->sole();

        $props = $this->props($this->actingAs($admin, 'admin')->get("/admin/parcels/import/{$import->id}"));

        $this->assertSame('article', $props['import']['options']['key']);
    }

    public function test_a_file_with_the_columns_of_an_earlier_import_is_mapped_and_set_up_as_that_one_was(): void
    {
        $this->makeImport(['mapping' => [null, 'code', 'price'], 'options' => ['key' => 'code', 'mode' => 'create']]);

        $this->upload();

        $import = DataImport::query()->latest('id')->first();
        $this->assertSame([null, 'code', 'price'], $import->mapping);
        $this->assertSame(['key' => 'code', 'mode' => 'create'], $import->options);
    }

    public function test_the_latest_import_with_those_columns_is_the_one_followed(): void
    {
        $this->makeImport(['mapping' => ['code', null, null], 'options' => ['key' => 'code', 'mode' => 'create']]);
        $this->makeImport(['mapping' => ['article', 'code', null], 'options' => ['key' => 'article', 'mode' => 'update']]);

        $this->upload();

        $this->assertSame(['article', 'code', null], DataImport::query()->latest('id')->first()->mapping);
    }

    public function test_an_earlier_import_is_not_followed_when_its_columns_differ_or_it_is_of_another_module_or_has_no_mapping(): void
    {
        $this->makeImport(['mapping' => [null, null, 'code']], "Article;Name;Cost\nA;B;C\n");
        $this->makeImport(['module' => 'posts', 'mapping' => [null, 'code', 'price']]);
        $this->makeImport(['mapping' => null]);
        $this->makeImport(['mapping' => [null, 'code', 'price']], "Name;Article;Price\nA;B;C\n");

        $this->upload();

        $import = DataImport::query()->latest('id')->first();
        $this->assertSame(['article', 'name', 'price'], $import->mapping);
        $this->assertSame(['key' => 'article'], $import->options);
    }

    public function test_only_the_last_twenty_imports_are_searched_for_the_same_columns(): void
    {
        $this->makeImport(['mapping' => [null, 'code', 'price']]);

        foreach (range(1, 20) as $number) {
            $this->makeImport(['mapping' => ['code', null, null]], "Other;Columns;Here\nA;B;C\n");
        }

        $this->upload();

        $this->assertSame(['article', 'name', 'price'], DataImport::query()->latest('id')->first()->mapping);
    }

    public function test_the_nineteenth_earlier_import_is_still_searched(): void
    {
        $this->makeImport(['mapping' => [null, 'code', 'price']]);

        foreach (range(1, 19) as $number) {
            $this->makeImport(['mapping' => ['code', null, null]], "Other;Columns;Here\nA;B;C\n");
        }

        $this->upload();

        $this->assertSame([null, 'code', 'price'], DataImport::query()->latest('id')->first()->mapping);
    }

    public function test_a_file_is_required_and_must_be_a_file(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post('/admin/parcels/import', [])
            ->assertSessionHasErrors(['file' => 'The file field is required.']);
        $this->actingAs($admin, 'admin')->post('/admin/parcels/import', ['file' => 'prices.csv'])
            ->assertSessionHasErrors(['file' => 'The file field must be a file.']);

        $this->assertSame(0, DataImport::query()->count());
    }

    public function test_a_file_larger_than_the_limit_is_refused_and_one_of_the_size_of_it_is_not(): void
    {
        config(['atrium.import.max_size' => 1]);
        $admin = $this->admin();
        $head = "Article;Name\n";

        $this->actingAs($admin, 'admin')->post('/admin/parcels/import', ['file' => UploadedFile::fake()->createWithContent('big.csv', $head.str_repeat('A-1;Parka'."\n", 114).'A-1;Parka'."\n")])
            ->assertSessionHasErrors(['file' => 'The file field must not be greater than 1 kilobytes.']);
        $this->assertSame(0, DataImport::query()->count());

        $exactly = $head.str_repeat("A-1;Parka\n", 101);
        $exactly = substr($exactly.str_repeat('x', 1024), 0, 1024);
        $this->assertSame(1024, strlen($exactly));
        $this->actingAs($admin, 'admin')->post('/admin/parcels/import', ['file' => UploadedFile::fake()->createWithContent('ok.csv', $exactly)])->assertSessionHasNoErrors();
        $this->assertSame(1, DataImport::query()->count());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function unreadable(): array
    {
        return [
            'an old Excel file' => ["\xD0\xCF\x11\xE0\x00\x00\x00\x00", 'This is not a CSV or Excel (xlsx) file. An old .xls file can be saved as .xlsx or CSV in Excel.'],
            'an empty file' => ['', 'The file has no rows.'],
            'a header alone' => ["Article;Name\n", 'The file has no rows.'],
            'a damaged archive' => ["PK\x03\x04garbage", 'The Excel file cannot be read: it may be damaged or protected with a password.'],
        ];
    }

    #[DataProvider('unreadable')]
    public function test_a_file_that_cannot_be_read_says_why_and_leaves_nothing_behind(string $content, string $message): void
    {
        $this->upload($content, 'bad.csv')->assertSessionHasErrors(['file' => $message]);

        $this->assertSame(0, DataImport::query()->count());
        $this->assertSame([], File::exists(DataImport::directory()) ? File::files(DataImport::directory()) : []);
    }

    public function test_a_guest_cannot_upload(): void
    {
        $this->post('/admin/parcels/import', ['file' => UploadedFile::fake()->createWithContent('a.csv', self::CSV)])->assertRedirect('/admin/login');

        $this->assertSame(0, DataImport::query()->count());
    }

    // The mapping screen

    public function test_the_mapping_screen_has_the_import_a_preview_of_the_file_and_what_the_module_offers(): void
    {
        $import = $this->makeImport(content: "Article;Name;Price\n".implode('', array_map(static fn (int $n): string => "A-{$n};Name {$n};{$n}\n", range(1, 7))));

        $response = $this->actingAs($this->admin(), 'admin')->get("/admin/parcels/import/{$import->id}");

        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Transfer/Import/Show'));
        $props = $this->props($response);

        $this->assertSame($import->id, $props['import']['id']);
        $this->assertSame(['parcels', 'admin.parcels', 'Parcels'], [$props['module'], $props['routeName'], $props['title']]);
        $this->assertSame([['A-1', 'Name 1', '1'], ['A-2', 'Name 2', '2'], ['A-3', 'Name 3', '3'], ['A-4', 'Name 4', '4'], ['A-5', 'Name 5', '5']], $props['preview']);
        $this->assertSame(['id', 'article', 'name', 'code', 'price', 'active', 'booking', 'starts_at', 'starts_on', 'label', 'colors', 'features', 'picture', 'photos', 'aisle_id', 'post_id', 'detail_text', 'sort'], array_column($props['targets'], 'key'));
        $this->assertSame([['key' => 'article', 'label' => 'Article'], ['key' => 'code', 'label' => 'Code'], ['key' => 'id', 'label' => 'ID']], $props['keys']);
        $this->assertSame(['both', 'create', 'update'], $props['modes']);
        $this->assertSame([['name' => 'separator', 'label' => 'Path separator', 'type' => 'text', 'default' => '/', 'hint' => 'Separates a parent from its child in a path: "Clothes / Jackets".']], $props['moduleOptions']);
    }

    public function test_a_file_of_five_rows_is_previewed_whole_and_a_module_without_a_tree_has_no_separator(): void
    {
        $import = $this->makeImport(['module' => 'posts', 'mapping' => ['name', null, null]], "Name;B;C\n".implode('', array_map(static fn (int $n): string => "P{$n};x;y\n", range(1, 5))));

        $props = $this->props($this->actingAs($this->admin(), 'admin')->get("/admin/posts/import/{$import->id}"));

        $this->assertCount(5, $props['preview']);
        $this->assertSame([], $props['moduleOptions']);
        $this->assertSame([['key' => 'id', 'label' => 'ID']], $props['keys']);
    }

    public function test_the_options_a_package_adds_are_on_the_screen(): void
    {
        Hook::filter('atrium.import.options', static fn (array $options): array => [...$options, new ImportOption('trim', 'Trim names', ImportOption::BOOLEAN, true, 'Hint')]);
        $import = $this->makeImport();

        $props = $this->props($this->actingAs($this->admin(), 'admin')->get("/admin/parcels/import/{$import->id}"));

        $this->assertSame(['separator', 'trim'], array_column($props['moduleOptions'], 'name'));
        $this->assertSame(['name' => 'trim', 'label' => 'Trim names', 'type' => 'boolean', 'default' => true, 'hint' => 'Hint'], $props['moduleOptions'][1]);
        $this->assertSame(['mode' => 'both', 'separator' => '/', 'trim' => true, 'key' => 'article'], $props['import']['options']);
    }

    public function test_an_import_whose_file_is_gone_has_no_preview(): void
    {
        $import = $this->makeImport();
        $import->deleteFile();

        $props = $this->props($this->actingAs($this->admin(), 'admin')->get("/admin/parcels/import/{$import->id}"));

        $this->assertSame([], $props['preview']);
        $this->assertSame($import->id, $props['import']['id']);
    }

    // Another module's import is not found, whichever way it is reached

    /**
     * @return array<string,array{string,string}> method, path after the import
     */
    public static function screens(): array
    {
        return [
            'the mapping screen' => ['get', ''],
            'the check' => ['put', ''],
            'the import' => ['post', '/run'],
            'the progress' => ['post', '/step'],
            'the stop' => ['post', '/cancel'],
        ];
    }

    #[DataProvider('screens')]
    public function test_an_import_of_another_module_is_not_found(string $method, string $path): void
    {
        $import = $this->makeImport(['module' => 'posts', 'status' => DataImport::CHECKED]);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->{$method}("/admin/parcels/import/{$import->id}{$path}", ['mapping' => ['article', 'name', 'price'], 'key' => 'article', 'mode' => 'both'])->assertNotFound();

        $import->refresh();
        $this->assertSame(DataImport::CHECKED, $import->status);
        $this->assertFileExists($import->path());
    }

    #[DataProvider('screens')]
    public function test_an_import_that_is_not_there_is_not_found(string $method, string $path): void
    {
        $this->actingAs($this->admin(), 'admin')->{$method}("/admin/parcels/import/999{$path}", ['mapping' => ['article'], 'key' => 'article', 'mode' => 'both'])->assertNotFound();
    }

    #[DataProvider('screens')]
    public function test_an_import_number_has_to_be_a_number(string $method, string $path): void
    {
        $this->actingAs($this->admin(), 'admin')->{$method}("/admin/parcels/import/abc{$path}")->assertNotFound();
    }

    // The check

    public function test_the_check_saves_the_mapping_and_the_options_and_starts(): void
    {
        $import = $this->makeImport(['mapping' => null, 'options' => null]);
        $import->forceFill(['processed' => 9, 'created' => 9, 'errors' => [['row' => 1, 'skipped' => false, 'messages' => ['x']]]])->save();

        $response = $this->actingAs($this->admin(), 'admin')->put("/admin/parcels/import/{$import->id}", [
            'mapping' => ['article', null, 'price'], 'key' => 'article', 'mode' => 'update', 'separator' => '::',
        ]);

        $response->assertRedirect("/admin/parcels/import/{$import->id}");
        $import->refresh();
        $this->assertSame([DataImport::CHECKING, ['article', null, 'price']], [$import->status, $import->mapping]);
        $this->assertSame(['key' => 'article', 'mode' => 'update', 'separator' => '::'], $import->options);
        $this->assertSame([0, 0, 0, []], [$import->position, $import->processed, $import->created, $import->errors]);
        $this->assertNotNull($import->started_at);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function open(): array
    {
        return ['new' => [DataImport::NEW], 'checked' => [DataImport::CHECKED], 'failed' => [DataImport::FAILED]];
    }

    #[DataProvider('open')]
    public function test_a_check_may_start_while_the_import_is_open(string $status): void
    {
        $import = $this->makeImport(['status' => $status]);

        $this->actingAs($this->admin(), 'admin')->put("/admin/parcels/import/{$import->id}", ['mapping' => ['article', 'name', 'price'], 'key' => 'article', 'mode' => 'both'])->assertRedirect();

        $this->assertSame(DataImport::CHECKING, $import->refresh()->status);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function closed(): array
    {
        return ['checking' => [DataImport::CHECKING], 'importing' => [DataImport::IMPORTING], 'done' => [DataImport::DONE], 'cancelled' => [DataImport::CANCELLED]];
    }

    #[DataProvider('closed')]
    public function test_a_check_cannot_start_otherwise(string $status): void
    {
        $import = $this->makeImport(['status' => $status, 'mapping' => ['code', null, null]]);

        $this->actingAs($this->admin(), 'admin')->put("/admin/parcels/import/{$import->id}", ['mapping' => ['article', 'name', 'price'], 'key' => 'article', 'mode' => 'both'])->assertStatus(409);

        $import->refresh();
        $this->assertSame([$status, ['code', null, null]], [$import->status, $import->mapping]);
    }

    public function test_a_mapping_that_does_not_fit_the_file_is_refused_and_nothing_changes(): void
    {
        $import = $this->makeImport();
        $admin = $this->admin();
        $put = fn (array $data): TestResponse => $this->actingAs($admin, 'admin')->put("/admin/parcels/import/{$import->id}", $data);

        $put(['mapping' => ['article', 'name'], 'key' => 'article', 'mode' => 'both'])->assertSessionHasErrors('mapping');
        $put(['mapping' => ['article', 'name', 'nope'], 'key' => 'article', 'mode' => 'both'])->assertSessionHasErrors('mapping.2');
        $put(['mapping' => ['article', 'article', 'price'], 'key' => 'article', 'mode' => 'both'])->assertSessionHasErrors(['mapping' => 'A field takes one column only; chosen more than once: Article.']);
        $put(['mapping' => [null, 'name', 'price'], 'key' => 'article', 'mode' => 'both'])->assertSessionHasErrors(['key' => 'Records are found by "Article", but no column fills it.']);
        $put(['mapping' => ['article', 'name', 'price'], 'key' => 'nope', 'mode' => 'both'])->assertSessionHasErrors('key');
        $put(['mapping' => ['article', 'name', 'price'], 'key' => 'article', 'mode' => 'nope'])->assertSessionHasErrors('mode');
        $put([])->assertSessionHasErrors(['mapping', 'key', 'mode']);

        $import->refresh();
        $this->assertSame(DataImport::NEW, $import->status);
        $this->assertSame(['article', 'name', 'price'], $import->mapping);
    }

    public function test_the_options_a_package_adds_are_validated_and_saved_with_the_check(): void
    {
        Hook::filter('atrium.import.options', static fn (array $options): array => [...$options, new ImportOption('trim', 'Trim names', ImportOption::BOOLEAN, false)]);
        $admin = $this->admin();
        $import = $this->makeImport();

        $this->actingAs($admin, 'admin')->put("/admin/parcels/import/{$import->id}", ['mapping' => ['article', 'name', 'price'], 'key' => 'article', 'mode' => 'both', 'trim' => 'abc'])->assertSessionHasErrors('trim');
        $this->actingAs($admin, 'admin')->put("/admin/parcels/import/{$import->id}", ['mapping' => ['article', 'name', 'price'], 'key' => 'article', 'mode' => 'both', 'trim' => '1'])->assertSessionHasNoErrors();

        // A JSON column on MySQL does not keep the order of the keys.
        $options = $import->refresh()->options;
        ksort($options);
        $this->assertSame(['key' => 'article', 'mode' => 'both', 'separator' => '/', 'trim' => true], $options);
    }

    public function test_with_a_queue_worker_the_check_is_queued_and_without_one_it_is_not(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $import = $this->makeImport();
        $data = ['mapping' => ['article', 'name', 'price'], 'key' => 'article', 'mode' => 'both'];

        $this->actingAs($admin, 'admin')->put("/admin/parcels/import/{$import->id}", $data);
        Queue::assertNothingPushed();

        config(['queue.default' => 'database']);
        $import->refresh()->forceFill(['status' => DataImport::NEW])->save();
        $this->actingAs($admin, 'admin')->put("/admin/parcels/import/{$import->id}", $data);

        Queue::assertPushed(ProcessImport::class, fn (ProcessImport $job): bool => $job->import === $import->id);
        Queue::assertPushed(ProcessImport::class, 1);
    }

    // The import

    public function test_what_has_been_checked_can_be_imported(): void
    {
        $import = $this->makeImport(['status' => DataImport::CHECKED, 'processed' => 3, 'created' => 3]);

        $this->actingAs($this->admin(), 'admin')->post("/admin/parcels/import/{$import->id}/run")->assertRedirect("/admin/parcels/import/{$import->id}");

        $import->refresh();
        $this->assertSame([DataImport::IMPORTING, 0, 0, 0], [$import->status, $import->position, $import->processed, $import->created]);
        $this->assertSame(0, Parcel::query()->count());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function notChecked(): array
    {
        return ['new' => [DataImport::NEW], 'checking' => [DataImport::CHECKING], 'importing' => [DataImport::IMPORTING], 'done' => [DataImport::DONE], 'failed' => [DataImport::FAILED], 'cancelled' => [DataImport::CANCELLED]];
    }

    #[DataProvider('notChecked')]
    public function test_only_a_checked_import_can_be_run(string $status): void
    {
        $import = $this->makeImport(['status' => $status]);

        $this->actingAs($this->admin(), 'admin')->post("/admin/parcels/import/{$import->id}/run")->assertStatus(409);

        $this->assertSame($status, $import->refresh()->status);
    }

    public function test_the_import_is_queued_with_a_queue_worker(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);
        $import = $this->makeImport(['status' => DataImport::CHECKED]);

        $this->actingAs($this->admin(), 'admin')->post("/admin/parcels/import/{$import->id}/run");

        Queue::assertPushed(ProcessImport::class, fn (ProcessImport $job): bool => $job->import === $import->id);
    }

    // The progress

    public function test_the_progress_page_moves_the_import_a_step_and_tells_where_it_stands_when_there_is_no_queue_worker(): void
    {
        $import = $this->makeImport();
        $import->begin(DataImport::IMPORTING);

        $response = $this->actingAs($this->admin(), 'admin')->postJson("/admin/parcels/import/{$import->id}/step");

        $response->assertOk()->assertJsonStructure(['id', 'status', 'original_name', 'format', 'encoding', 'delimiter', 'headers', 'mapping', 'total', 'processed', 'created', 'updated', 'skipped', 'failed', 'nodes', 'errors', 'message', 'options', 'user', 'created_at', 'started_at', 'finished_at']);
        $this->assertSame([$import->id, 'done', 3, 3, 3, 0, 0], [
            $response->json('id'), $response->json('status'), $response->json('total'), $response->json('processed'), $response->json('created'), $response->json('failed'), $response->json('updated'),
        ]);
        $this->assertSame(3, Parcel::query()->count());
        $this->assertFileDoesNotExist($import->path());
    }

    public function test_a_check_moves_a_step_the_same_way_and_ends_checked(): void
    {
        $import = $this->makeImport();
        $import->begin(DataImport::CHECKING);

        $response = $this->actingAs($this->admin(), 'admin')->postJson("/admin/parcels/import/{$import->id}/step");

        $this->assertSame(['checked', 3], [$response->json('status'), $response->json('processed')]);
        $this->assertSame(0, Parcel::query()->count());
        $this->assertFileExists($import->path());
    }

    public function test_the_progress_page_only_looks_when_a_queue_worker_does_the_work(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);
        $import = $this->makeImport();
        $import->begin(DataImport::IMPORTING);

        $response = $this->actingAs($this->admin(), 'admin')->postJson("/admin/parcels/import/{$import->id}/step");

        $this->assertSame(['importing', 0], [$response->json('status'), $response->json('processed')]);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_the_progress_of_an_import_that_is_not_running_is_just_its_state(): void
    {
        $import = $this->makeImport(['status' => DataImport::CHECKED, 'processed' => 3]);

        $response = $this->actingAs($this->admin(), 'admin')->postJson("/admin/parcels/import/{$import->id}/step");

        $this->assertSame(['checked', 3], [$response->json('status'), $response->json('processed')]);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_the_progress_runs_for_the_seconds_the_request_may_take(): void
    {
        config(['atrium.import.request_seconds' => 5, 'atrium.import.chunk' => 1]);
        $import = $this->makeImport();
        $import->begin(DataImport::IMPORTING);

        // Time stands still, so a step that is given seconds does the whole file; one that is given none, a row.
        $first = $this->actingAs($this->admin(), 'admin')->postJson("/admin/parcels/import/{$import->id}/step");
        $this->assertSame(3, $first->json('processed'));

        config(['atrium.import.request_seconds' => 0]);
        $second = $this->makeImport();
        $second->begin(DataImport::IMPORTING);
        $this->postJson("/admin/parcels/import/{$second->id}/step");

        $this->assertSame(1, $second->refresh()->processed);
        $this->assertSame(DataImport::IMPORTING, $second->status);
    }

    // The stop

    public function test_an_import_under_way_can_be_stopped_and_its_file_goes(): void
    {
        $import = $this->makeImport(['status' => DataImport::IMPORTING]);

        $this->actingAs($this->admin(), 'admin')->post("/admin/parcels/import/{$import->id}/cancel")->assertRedirect("/admin/parcels/import/{$import->id}");

        $import->refresh();
        $this->assertSame(DataImport::CANCELLED, $import->status);
        $this->assertNotNull($import->finished_at);
        $this->assertFileDoesNotExist($import->path());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function stoppable(): array
    {
        return ['new' => [DataImport::NEW], 'checking' => [DataImport::CHECKING], 'checked' => [DataImport::CHECKED], 'importing' => [DataImport::IMPORTING], 'failed' => [DataImport::FAILED], 'cancelled' => [DataImport::CANCELLED]];
    }

    #[DataProvider('stoppable')]
    public function test_any_import_that_is_not_over_can_be_stopped(string $status): void
    {
        $import = $this->makeImport(['status' => $status]);

        $this->actingAs($this->admin(), 'admin')->post("/admin/parcels/import/{$import->id}/cancel")->assertRedirect();

        $this->assertSame(DataImport::CANCELLED, $import->refresh()->status);
        $this->assertFileDoesNotExist($import->path());
    }

    public function test_an_import_that_is_over_stays_over(): void
    {
        $import = $this->makeImport(['status' => DataImport::DONE, 'finished_at' => '2026-11-22 10:00:00']);

        $this->actingAs($this->admin(), 'admin')->post("/admin/parcels/import/{$import->id}/cancel")->assertRedirect("/admin/parcels/import/{$import->id}");

        $import->refresh();
        $this->assertSame(DataImport::DONE, $import->status);
        $this->assertSame('2026-11-22 10:00:00', $import->finished_at->format('Y-m-d H:i:s'));
        $this->assertFileExists($import->path());
    }

    // Who may

    /**
     * @return array<string,array{string,string}>
     */
    public static function routes(): array
    {
        return [
            'the list' => ['get', ''],
            'the upload' => ['post', ''],
            'the mapping screen' => ['get', '/{import}'],
            'the check' => ['put', '/{import}'],
            'the import' => ['post', '/{import}/run'],
            'the progress' => ['post', '/{import}/step'],
            'the stop' => ['post', '/{import}/cancel'],
        ];
    }

    #[DataProvider('routes')]
    public function test_every_screen_needs_the_import_permission_of_the_module(string $method, string $path): void
    {
        $import = $this->makeImport();
        $every = $this->editor(['parcels.view', 'parcels.create', 'parcels.update', 'parcels.delete', 'parcels.export', 'posts.import', 'posts.view'], 'no@example.com');

        $this->actingAs($every, 'admin')->{$method}('/admin/parcels/import'.str_replace('{import}', (string) $import->id, $path))->assertForbidden();

        $this->assertSame(DataImport::NEW, $import->refresh()->status);
        $this->assertSame(1, DataImport::query()->count());
    }

    #[DataProvider('routes')]
    public function test_the_import_permission_alone_is_enough(string $method, string $path): void
    {
        $import = $this->makeImport();
        $only = $this->editor(['parcels.import'], 'yes@example.com');

        $status = $this->actingAs($only, 'admin')->{$method}('/admin/parcels/import'.str_replace('{import}', (string) $import->id, $path), ['file' => UploadedFile::fake()->createWithContent('a.csv', self::CSV)])->status();

        $this->assertNotSame(403, $status);
    }

    public function test_a_module_that_opts_out_has_no_import_screens(): void
    {
        $admin = $this->admin();

        foreach (['no_import_shelves', 'sites', 'languages'] as $module) {
            $this->assertFalse(Route::has("admin.{$module}.import"), $module);
            $this->assertFalse(Route::has("admin.{$module}.import.store"), $module);
            $this->assertContains($this->actingAs($admin, 'admin')->get("/admin/{$module}/import")->status(), [404, 405], $module);
            $this->assertContains($this->actingAs($admin, 'admin')->post("/admin/{$module}/import", ['file' => UploadedFile::fake()->createWithContent('a.csv', self::CSV)])->status(), [404, 405], $module);
        }

        $this->assertTrue(Route::has('admin.no_export_shelves.import'));
        $this->assertSame(0, DataImport::query()->count());
    }

    public function test_a_module_that_is_not_there_is_not_found(): void
    {
        $controller = app(ImportController::class);

        $this->expectException(NotFoundHttpException::class);

        $controller->index('nothing');
    }

    /**
     * @return array<string,array{string}>
     */
    public static function actions(): array
    {
        return ['index' => ['index'], 'store' => ['store'], 'show' => ['show'], 'check' => ['check'], 'run' => ['run'], 'step' => ['step'], 'cancel' => ['cancel']];
    }

    #[DataProvider('actions')]
    public function test_every_action_looks_the_module_up_first(string $action): void
    {
        $import = $this->makeImport();
        $arguments = match ($action) {
            'index' => ['nothing'],
            'store' => [Request::create('/', 'POST'), 'nothing', app(SourceFile::class)],
            'step' => [$import, 'nothing', app(ImportRunner::class)],
            'check' => [Request::create('/', 'PUT'), $import, 'nothing'],
            default => [$import, 'nothing'],
        };

        $this->expectException(NotFoundHttpException::class);

        app(ImportController::class)->{$action}(...$arguments);
    }
}
