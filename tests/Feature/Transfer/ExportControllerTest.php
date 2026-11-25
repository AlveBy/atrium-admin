<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Http\Controllers\ExportController;
use Atrium\Admin\Tests\Concerns\CreatesEditors;
use Atrium\Admin\Tests\Fixtures\Bulletin;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Transfer\NoExportShelf;
use Atrium\Admin\Tests\Fixtures\Transfer\NoImportShelf;
use Atrium\Admin\Transfer\ImportFailed;
use Atrium\Admin\Transfer\SourceFile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The export button: the list of a module, as the list shows it, in a file.
 */
final class ExportControllerTest extends TransferTestCase
{
    use CreatesEditors;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Parcel::class, Post::class, Bulletin::class, NoExportShelf::class, NoImportShelf::class]);
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
     * What the download holds, read back as the import reads a file: the headers and the first cell of every row.
     *
     * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    private function read(TestResponse $response, string $extension): array
    {
        File::ensureDirectoryExists($this->directory);
        $copy = $this->directory.'/download.'.$extension;
        copy($response->baseResponse->getFile()->getPathname(), $copy);

        try {
            $read = (new SourceFile)->normalize($copy, $this->directory.'/read.txt');
        } catch (ImportFailed) {
            // A header and no records is a file the import has nothing to read in.
            return ['headers' => [], 'rows' => []];
        }

        $rows = array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), file($this->directory.'/read.txt', FILE_IGNORE_NEW_LINES));

        return ['headers' => $read['headers'], 'rows' => $rows];
    }

    /**
     * @return array<int,string> the article of every row, in order
     */
    private function articles(TestResponse $response, string $extension = 'csv'): array
    {
        return array_column($this->read($response, $extension)['rows'], 2);
    }

    private function parcels(): void
    {
        foreach ([['Alpha', 'A-1', 3], ['Bravo', 'A-2', 1], ['Charlie', 'A-3', 2], ['Delta', 'A-4', 1]] as [$name, $article, $sort]) {
            Parcel::query()->create(['name' => $name, 'article' => $article, 'sort' => $sort]);
        }
    }

    public function test_the_list_is_a_csv_by_default_named_for_the_module_and_the_day(): void
    {
        $this->parcels();

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/parcels/export');

        $response->assertOk();
        $response->assertDownload('parcels-2026-11-22.csv');
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertStringContainsString('attachment; filename=parcels-2026-11-22.csv', (string) $response->headers->get('content-disposition'));
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('content-type'));
        $this->assertStringStartsWith("\xEF\xBB\xBFID;Article;Name;Code;", (string) file_get_contents($response->baseResponse->getFile()->getPathname()));
        $this->assertSame(['A-4', 'A-3', 'A-2', 'A-1'], $this->articles($response));
    }

    public function test_it_is_an_excel_file_when_asked_for_one(): void
    {
        $this->parcels();

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/parcels/export?format=xlsx');

        $response->assertOk();
        $response->assertDownload('parcels-2026-11-22.xlsx');
        $this->assertStringContainsString('attachment; filename=parcels-2026-11-22.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));
        $this->assertSame('PK', substr((string) file_get_contents($response->baseResponse->getFile()->getPathname()), 0, 2));

        $read = $this->read($response, 'xlsx');

        $this->assertSame(['ID', 'Article', 'Name', 'Code', 'Price', 'Active', 'Booking', 'Starts at', 'Starts on', 'Label', 'Colours', 'Features', 'Picture', 'Photos', 'Aisle', 'Post', 'Description', 'Sort'], $read['headers']);
        $this->assertSame(['A-4', 'A-3', 'A-2', 'A-1'], array_column($read['rows'], 2));
    }

    public function test_an_explicit_csv_is_a_csv(): void
    {
        $this->parcels();

        $this->actingAs($this->admin(), 'admin')->get('/admin/parcels/export?format=csv')->assertDownload('parcels-2026-11-22.csv');
    }

    public function test_the_module_in_the_name_is_the_one_exported(): void
    {
        Post::query()->create(['name' => 'Hello']);

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/posts/export');

        $response->assertDownload('posts-2026-11-22.csv');
        $this->assertSame(['ID', 'Name', 'Active', 'Publish at', 'Unpublish at', 'Sort'], $this->read($response, 'csv')['headers']);
        $this->assertSame([['2', '1', 'Hello', '1', '', '', '500']], $this->read($response, 'csv')['rows']);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function badFormats(): array
    {
        return ['pdf' => ['pdf'], 'upper case' => ['CSV'], 'xls' => ['xls'], 'a list of formats' => ['csv,xlsx'], 'excel by its long name' => ['xlsx2']];
    }

    #[DataProvider('badFormats')]
    public function test_any_other_format_is_refused(string $format): void
    {
        $this->parcels();

        $this->actingAs($this->admin(), 'admin')->get('/admin/parcels/export?format='.rawurlencode($format))->assertStatus(422);
    }

    public function test_an_empty_format_is_the_default_one_and_the_blanks_around_it_are_trimmed(): void
    {
        $this->parcels();
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('/admin/parcels/export?format=')->assertDownload('parcels-2026-11-22.csv');
        $this->actingAs($admin, 'admin')->get('/admin/parcels/export?format=xlsx%20')->assertDownload('parcels-2026-11-22.xlsx');
    }

    public function test_the_search_of_the_list_narrows_the_file(): void
    {
        $this->parcels();
        $admin = $this->admin();

        $this->assertSame(['A-2'], $this->articles($this->actingAs($admin, 'admin')->get('/admin/parcels/export?search=rav')));
        $this->assertSame(['A-4', 'A-3', 'A-1'], $this->articles($this->actingAs($admin, 'admin')->get('/admin/parcels/export?search=l')));
        $this->assertSame([], $this->articles($this->actingAs($admin, 'admin')->get('/admin/parcels/export?search=nothing')));
        $this->assertSame(['A-2'], $this->articles($this->actingAs($admin, 'admin')->get('/admin/parcels/export?format=xlsx&search=BRAVO'), 'xlsx'));
    }

    public function test_the_sort_of_the_list_orders_the_file_and_the_ones_it_cannot_tell_apart_go_by_id(): void
    {
        $this->parcels();
        $admin = $this->admin();

        $this->assertSame(['A-1', 'A-2', 'A-3', 'A-4'], $this->articles($this->actingAs($admin, 'admin')->get('/admin/parcels/export?sort=name&direction=asc')));
        $this->assertSame(['A-4', 'A-3', 'A-2', 'A-1'], $this->articles($this->actingAs($admin, 'admin')->get('/admin/parcels/export?sort=name&direction=desc')));
        $this->assertSame(['A-2', 'A-4', 'A-3', 'A-1'], $this->articles($this->actingAs($admin, 'admin')->get('/admin/parcels/export?sort=sort&direction=asc')));
        $this->assertSame(['A-1', 'A-3', 'A-2', 'A-4'], $this->articles($this->actingAs($admin, 'admin')->get('/admin/parcels/export?sort=sort&direction=desc')));
    }

    public function test_the_filters_of_the_module_narrow_the_file(): void
    {
        foreach ([['Alpha', 1], ['Beta', 0], ['Gamma', 1]] as [$name, $active]) {
            Bulletin::query()->create(['name' => $name, 'active' => $active]);
        }

        $admin = $this->admin();
        $names = fn (string $query): array => array_column($this->read($this->actingAs($admin, 'admin')->get('/admin/bulletins/export'.$query), 'csv')['rows'], 2);

        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $names(''));
        $this->assertSame(['Gamma', 'Alpha'], $names('?active=1'));
        $this->assertSame(['Beta'], $names('?active=0'));
        $this->assertSame(['Beta'], $names('?name=Beta'));
        $this->assertSame([], $names('?name=Beta&active=1'));
        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $names('?name='));
        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $names('?other=1'));
    }

    public function test_the_page_of_the_list_does_not_cut_the_file(): void
    {
        foreach (range(1, 30) as $number) {
            Post::query()->create(['name' => "Post {$number}"]);
        }

        $rows = $this->read($this->actingAs($this->admin(), 'admin')->get('/admin/posts/export?page=2'), 'csv')['rows'];

        $this->assertCount(30, $rows);
        $this->assertSame('Post 30', $rows[0][2]);
        $this->assertSame('Post 1', $rows[29][2]);
    }

    public function test_the_file_is_taken_from_the_server_once_it_is_sent(): void
    {
        $this->parcels();

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/parcels/export');
        $path = $response->baseResponse->getFile()->getPathname();

        $this->assertFileExists($path);
        $this->assertStringStartsWith(realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'atrium-export-', $path);

        ob_start();
        $response->baseResponse->sendContent();
        $sent = (string) ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBFID;Article;", $sent);
        $this->assertFileDoesNotExist($path);
    }

    public function test_it_needs_the_export_permission_of_the_module(): void
    {
        $this->parcels();
        $allowed = $this->editor(['parcels.export'], 'a@example.com');
        $others = $this->editor(['parcels.view', 'parcels.create', 'parcels.update', 'parcels.delete', 'parcels.import', 'posts.export'], 'b@example.com');

        $this->actingAs($allowed, 'admin')->get('/admin/parcels/export')->assertOk();
        $this->actingAs($allowed, 'admin')->get('/admin/posts/export')->assertForbidden();
        $this->actingAs($others, 'admin')->get('/admin/parcels/export')->assertForbidden();
        $this->actingAs($others, 'admin')->get('/admin/posts/export')->assertOk();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/admin/parcels/export')->assertRedirect('/admin/login');
    }

    public function test_a_module_that_opts_out_has_no_export_and_the_others_keep_theirs(): void
    {
        $admin = $this->admin();

        $this->assertFalse(Route::has('admin.no_export_shelves.export'));
        $this->assertTrue(Route::has('admin.no_import_shelves.export'));
        $this->assertContains($this->actingAs($admin, 'admin')->get('/admin/no_export_shelves/export')->status(), [404, 405]);
        $this->actingAs($admin, 'admin')->get('/admin/no_import_shelves/export')->assertOk();
    }

    public function test_the_sites_and_the_languages_can_be_exported(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('/admin/sites/export')->assertOk()->assertDownload('sites-2026-11-22.csv');
        $this->actingAs($admin, 'admin')->get('/admin/languages/export')->assertOk()->assertDownload('languages-2026-11-22.csv');
    }

    public function test_a_module_that_is_not_there_is_not_found(): void
    {
        $request = Request::create('/admin/nothing/export');

        $this->expectException(NotFoundHttpException::class);

        app(ExportController::class)->download($request, 'nothing');
    }

    public function test_a_bad_format_is_a_422_before_any_file_is_made(): void
    {
        $before = glob(sys_get_temp_dir().'/atrium-export-*') ?: [];

        try {
            app(ExportController::class)->download(Request::create('/', 'GET', ['format' => 'pdf']), 'parcels');
            $this->fail('A 422 was expected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame($before, glob(sys_get_temp_dir().'/atrium-export-*') ?: []);
    }
}
