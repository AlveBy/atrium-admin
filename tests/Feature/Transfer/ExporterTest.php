<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Tests\Fixtures\ApiAisle;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Transfer\Dated;
use Atrium\Admin\Transfer\Exporter;
use Atrium\Admin\Transfer\Output\Output;
use Atrium\Admin\Transfer\RecordWriter;
use Atrium\Admin\Transfer\SourceFile;
use Atrium\Admin\Transfer\Target;
use Atrium\Admin\Transfer\Targets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * What an export writes: a header, then a row for each record, a cell for
 * each field the import can fill.
 */
final class ExporterTest extends TransferTestCase
{
    private const HEADER = ['ID', 'Article', 'Name', 'Code', 'Price', 'Active', 'Booking', 'Starts at', 'Starts on', 'Label', 'Colours', 'Features', 'Picture', 'Photos', 'Aisle', 'Post', 'Description', 'Sort'];

    /**
     * @param  class-string<Model>  $model
     * @return array<int,array<int,string>> every row written, the header first
     */
    private function rows(string $model = Parcel::class, ?object $query = null): array
    {
        $output = new class implements Output
        {
            /** @var array<int,array<int,string>> */
            public array $rows = [];

            public bool $closed = false;

            public function row(array $cells): void
            {
                $this->rows[] = $cells;
            }

            public function close(): void
            {
                $this->closed = true;
            }
        };

        Exporter::for($model)->write($output, $query ?? $model::query());

        $this->assertTrue($output->closed);

        return $output->rows;
    }

    public function test_the_header_is_the_label_of_every_field_the_import_can_fill_with_the_id_first(): void
    {
        $this->assertSame(self::HEADER, Exporter::for(Parcel::class)->header());
        $this->assertSame(self::HEADER, array_column(Targets::for(Parcel::class)->options(), 'label'));
        $this->assertSame(['ID', 'Name', 'Active', 'Sort'], Exporter::for(Post::class)->header());
    }

    public function test_a_file_has_the_header_and_nothing_else_when_there_are_no_records(): void
    {
        $this->assertSame([self::HEADER], $this->rows());
        $this->assertSame(0, Exporter::for(Parcel::class)->write($this->sink(), Parcel::query()));
    }

    public function test_a_record_is_a_row_with_a_cell_of_text_for_every_field(): void
    {
        $post = Post::query()->create(['name' => 'Hello']);
        $top = ApiAisle::query()->create(['name' => 'Europe']);
        $child = ApiAisle::query()->create(['name' => 'Day trips', 'parent_id' => $top->id]);
        Parcel::query()->create([
            'article' => 'A-1', 'name' => 'Parka', 'code' => 'parka', 'price' => '1299.50', 'active' => true, 'booking' => 'YES',
            'starts_at' => '2026-03-05 10:30:00', 'starts_on' => '2026-03-05', 'label' => 'hit', 'colors' => ['black', 'silver'],
            'features' => ['Warm', 'Light'], 'picture' => 'a.jpg', 'photos' => ['a.jpg', 'b.jpg'], 'aisle_id' => $child->id,
            'post_id' => $post->id, 'detail_text' => "Two\nlines", 'sort' => 10,
        ]);

        $rows = $this->rows();

        $this->assertCount(2, $rows);
        $this->assertSame(
            ['1', 'A-1', 'Parka', 'parka', '1299.50', '1', '1', '2026-03-05 10:30:00', '2026-03-05', 'hit', 'black|silver', 'Warm|Light', 'a.jpg', 'a.jpg|b.jpg', 'Europe / Day trips', (string) $post->id, "Two\nlines", '10'],
            $rows[1],
        );
    }

    public function test_a_record_with_nothing_filled_in_is_empty_cells_and_the_defaults(): void
    {
        Parcel::query()->create(['article' => 'A-2', 'name' => 'Coat', 'active' => false, 'booking' => 'NO']);

        $this->assertSame(
            ['1', 'A-2', 'Coat', '', '', '0', '0', '', '', '', '', '', '', '', '', '', '', '500'],
            $this->rows()[1],
        );
    }

    public function test_a_yes_no_field_is_one_or_zero_whatever_it_keeps(): void
    {
        foreach ([['active' => true, 'booking' => 'YES'], ['active' => false, 'booking' => 'NO'], ['active' => true, 'booking' => 'maybe'], ['active' => true, 'booking' => null]] as $values) {
            Parcel::query()->create(['name' => 'x', ...$values]);
        }

        $rows = $this->rows();

        $this->assertSame(['1', '1'], [$rows[1][5], $rows[1][6]]);
        $this->assertSame(['0', '0'], [$rows[2][5], $rows[2][6]]);
        $this->assertSame(['1', '0'], [$rows[3][5], $rows[3][6]]);
        $this->assertSame(['1', ''], [$rows[4][5], $rows[4][6]]);
    }

    public function test_a_choice_of_several_and_a_list_are_one_cell_split_by_a_bar_and_an_empty_one_is_empty(): void
    {
        Parcel::query()->create(['name' => 'one', 'colors' => ['black'], 'features' => ['Warm']]);
        Parcel::query()->create(['name' => 'none', 'colors' => [], 'features' => []]);

        $rows = $this->rows();

        $this->assertSame(['black', 'Warm'], [$rows[1][10], $rows[1][11]]);
        $this->assertSame(['', ''], [$rows[2][10], $rows[2][11]]);
    }

    public function test_a_tree_field_is_the_path_from_the_top_and_a_top_node_its_name(): void
    {
        $top = ApiAisle::query()->create(['name' => 'Europe']);
        $mid = ApiAisle::query()->create(['name' => 'Day trips', 'parent_id' => $top->id]);
        $deep = ApiAisle::query()->create(['name' => 'Prague', 'parent_id' => $mid->id]);
        Parcel::query()->create(['name' => 'a', 'aisle_id' => $deep->id]);
        Parcel::query()->create(['name' => 'b', 'aisle_id' => $top->id]);
        Parcel::query()->create(['name' => 'c', 'aisle_id' => 999]);

        $rows = $this->rows();

        $this->assertSame('Europe / Day trips / Prague', $rows[1][14]);
        $this->assertSame('Europe', $rows[2][14]);
        $this->assertSame('', $rows[3][14]);
    }

    public function test_a_link_to_another_module_is_the_ones_id(): void
    {
        $first = Post::query()->create(['name' => 'One']);
        $second = Post::query()->create(['name' => 'Two']);
        Parcel::query()->create(['name' => 'a', 'post_id' => $second->id]);
        Parcel::query()->create(['name' => 'b', 'post_id' => $first->id]);

        $rows = $this->rows();

        $this->assertSame([(string) $second->id, (string) $first->id], [$rows[1][15], $rows[2][15]]);
    }

    public function test_dates_are_written_the_way_the_import_reads_them(): void
    {
        Parcel::query()->create(['name' => 'a', 'starts_at' => '2026-12-31 23:59:59', 'starts_on' => '2026-12-31']);
        Parcel::query()->create(['name' => 'b', 'starts_at' => '2026-01-02 00:00:00', 'starts_on' => '2026-01-02']);

        $rows = $this->rows();

        $this->assertSame(['2026-12-31 23:59:59', '2026-12-31'], [$rows[1][7], $rows[1][8]]);
        $this->assertSame(['2026-01-02 00:00:00', '2026-01-02'], [$rows[2][7], $rows[2][8]]);
    }

    public function test_a_date_is_the_one_in_the_database_whatever_the_time_zone_of_the_application(): void
    {
        $was = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        try {
            $parcel = Parcel::query()->create(['name' => 'a', 'starts_at' => '2026-03-05 10:30:00', 'starts_on' => '2026-03-05']);
            $fresh = Parcel::query()->find($parcel->id);
            $row = $this->rows()[1];

            // What the form is given is in UTC; the file has what the list shows.
            $this->assertSame('2026-03-05T01:30:00.000000Z', (new RecordWriter(Parcel::class))->present($fresh)['starts_at']);
            $this->assertSame(['2026-03-05 10:30:00', '2026-03-05'], [$row[7], $row[8]]);
        } finally {
            date_default_timezone_set($was);
        }
    }

    public function test_a_date_the_model_does_not_cast_is_written_as_it_is_kept(): void
    {
        DB::table('parcels')->insert(['article' => 'a', 'name' => '{}', 'starts_at' => '2026-03-05 10:30:00', 'starts_on' => '2026-03-05', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('parcels')->insert(['article' => 'b', 'name' => '{}', 'starts_at' => null, 'starts_on' => null, 'created_at' => now(), 'updated_at' => now()]);

        $rows = $this->rows(Dated::class);

        $this->assertSame(['ID', 'Article', 'Starts at', 'Starts on'], $rows[0]);
        $this->assertSame(['1', 'a', '2026-03-05 10:30:00', '2026-03-05'], $rows[1]);
        $this->assertSame(['2', 'b', '', ''], $rows[2]);
    }

    public function test_a_field_kept_per_language_is_a_column_for_each_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        Parcel::query()->create(['name' => ['en' => 'Parka', 'de' => 'Anorak']]);
        Parcel::query()->create(['name' => ['en' => 'Coat']]);
        Parcel::query()->create(['name' => ['de' => 'Mantel']]);

        $rows = $this->rows();

        $this->assertSame(['ID', 'Article', 'Name (English)', 'Name (Deutsch)', 'Code'], array_slice($rows[0], 0, 5));
        $this->assertSame(['1', '', 'Parka', 'Anorak', ''], array_slice($rows[1], 0, 5));
        $this->assertSame(['2', '', 'Coat', '', ''], array_slice($rows[2], 0, 5));
        $this->assertSame(['3', '', '', 'Mantel', ''], array_slice($rows[3], 0, 5));
        $this->assertCount(19, $rows[0]);
        $this->assertCount(19, $rows[1]);
    }

    public function test_with_one_language_a_field_kept_per_language_is_a_single_column_with_the_default_ones_text(): void
    {
        Parcel::query()->create(['name' => ['en' => 'Parka', 'de' => 'Anorak']]);
        Parcel::query()->create(['name' => ['de' => 'Mantel']]);

        $rows = $this->rows();

        $this->assertSame(['ID', 'Article', 'Name', 'Code'], array_slice($rows[0], 0, 4));
        $this->assertSame('Parka', $rows[1][2]);
        $this->assertSame('', $rows[2][2]);
        $this->assertCount(18, $rows[1]);
    }

    public function test_a_target_that_writes_its_own_cell_is_asked_for_it_even_where_a_field_stands_behind(): void
    {
        Hook::filter('atrium.import.targets', static function (array $targets): array {
            $targets['code'] = new Target('code', 'Code', $targets['code']->field, null, null, [], null, static fn (Model $record): string => 'code-'.$record->getKey());
            $targets['extra'] = new Target('extra', 'Extra', null, null, null, [], null, static fn (Model $record): string => 'x'.$record->getKey());

            return $targets;
        });
        Parcel::query()->create(['name' => 'a', 'code' => 'own']);

        $rows = $this->rows();

        $this->assertSame('Extra', $rows[0][18]);
        $this->assertSame('Code', $rows[0][3]);
        $this->assertSame('code-1', $rows[1][3]);
        $this->assertSame('x1', $rows[1][18]);
        $this->assertSame('1', $rows[1][0]);
    }

    public function test_the_records_come_in_the_order_of_the_query_and_those_it_does_not_tell_apart_by_id(): void
    {
        foreach ([['c', 2], ['a', 1], ['b', 2], ['d', 1], ['e', 2]] as [$article, $sort]) {
            Parcel::query()->create(['name' => $article, 'article' => $article, 'sort' => $sort]);
        }

        $ascending = array_column($this->rows(Parcel::class, Parcel::query()->orderBy('sort')), 1);
        $descending = array_column($this->rows(Parcel::class, Parcel::query()->orderBy('sort', 'desc')), 1);
        $plain = array_column($this->rows(), 1);

        $this->assertSame(['Article', 'a', 'd', 'c', 'b', 'e'], $ascending);
        $this->assertSame(['Article', 'c', 'b', 'e', 'a', 'd'], $descending);
        $this->assertSame(['Article', 'c', 'a', 'b', 'd', 'e'], $plain);

        DB::enableQueryLog();
        $this->rows(Parcel::class, Parcel::query()->orderBy('sort', 'desc'));
        $selects = array_column(array_filter(DB::getQueryLog(), static fn (array $query): bool => preg_match('/from\W+parcels\W/', $query['query']) === 1), 'query');

        $this->assertCount(1, $selects);
        $this->assertMatchesRegularExpression('/order by \W?sort\W? desc, \W?parcels\W?\.\W?id\W? asc/', $selects[0]);
    }

    public function test_the_records_are_read_a_chunk_at_a_time_and_none_is_missed_or_repeated(): void
    {
        foreach (range(1, 5) as $number) {
            Parcel::query()->create(['name' => "n{$number}", 'article' => "A{$number}"]);
        }

        config(['atrium.export.chunk' => 2]);
        DB::enableQueryLog();

        $count = Exporter::for(Parcel::class)->write($this->sink(), Parcel::query()->orderBy('id'));
        $selects = $this->selects();

        $this->assertSame(5, $count);
        $this->assertCount(3, $selects);
        $this->assertMatchesRegularExpression('/limit 2 offset 0/', $selects[0]['query']);
        $this->assertMatchesRegularExpression('/limit 2 offset 2/', $selects[1]['query']);
        $this->assertMatchesRegularExpression('/limit 2 offset 4/', $selects[2]['query']);
    }

    public function test_the_chunks_are_cut_where_the_number_of_records_is_a_multiple_of_the_chunk_and_where_it_is_not(): void
    {
        foreach (range(1, 4) as $number) {
            Parcel::query()->create(['name' => "n{$number}", 'article' => "A{$number}"]);
        }

        // 4 records, chunks of 2: two full ones and an empty one that ends it.
        config(['atrium.export.chunk' => 2]);
        DB::enableQueryLog();
        $this->assertSame(4, Exporter::for(Parcel::class)->write($this->sink(), Parcel::query()));
        $selects = $this->selects();
        $this->assertCount(3, $selects);
        $this->assertMatchesRegularExpression('/limit 2 offset 4/', $selects[2]['query']);

        // One chunk as large as the table, and one larger.
        foreach ([4, 5, 500] as $chunk) {
            config(['atrium.export.chunk' => $chunk]);
            DB::flushQueryLog();
            $this->assertSame(4, Exporter::for(Parcel::class)->write($this->sink(), Parcel::query()), "chunk {$chunk}");
            $this->assertCount($chunk === 4 ? 2 : 1, $this->selects(), "chunk {$chunk}");
        }

        // A chunk of one: a query for each record, and one more to find the end.
        config(['atrium.export.chunk' => 1]);
        DB::flushQueryLog();
        $this->assertSame(4, Exporter::for(Parcel::class)->write($this->sink(), Parcel::query()));
        $this->assertCount(5, $this->selects());
    }

    public function test_the_rows_of_a_chunked_export_are_all_there_in_order(): void
    {
        foreach (range(1, 7) as $number) {
            Parcel::query()->create(['name' => "n{$number}", 'article' => "A{$number}", 'sort' => $number % 2]);
        }

        config(['atrium.export.chunk' => 3]);
        $rows = $this->rows(Parcel::class, Parcel::query()->orderBy('sort'));

        $this->assertSame(['Article', 'A2', 'A4', 'A6', 'A1', 'A3', 'A5', 'A7'], array_column($rows, 1));
        $this->assertSame(['ID', '2', '4', '6', '1', '3', '5', '7'], array_column($rows, 0));
    }

    public function test_the_header_comes_first_the_rows_next_and_the_output_is_closed_at_the_end(): void
    {
        Parcel::query()->create(['name' => 'a', 'article' => 'A1']);
        Parcel::query()->create(['name' => 'b', 'article' => 'A2']);

        $log = [];
        $output = new class($log) implements Output
        {
            /** @param array<int,string> $log */
            public function __construct(private array &$log) {}

            public function row(array $cells): void
            {
                $this->log[] = 'row '.$cells[0];
            }

            public function close(): void
            {
                $this->log[] = 'close';
            }
        };

        $count = Exporter::for(Parcel::class)->write($output, Parcel::query());

        $this->assertSame(2, $count);
        $this->assertSame(['row ID', 'row 1', 'row 2', 'close'], $log);
    }

    public function test_a_csv_formula_is_protected_and_an_excel_cell_stays_as_it_is(): void
    {
        Parcel::query()->create(['name' => '=SUM(1+1)', 'article' => '+cmd', 'code' => '@x', 'detail_text' => '-5', 'sort' => 1]);
        $query = fn () => Parcel::query();

        $csv = $this->directory.'/e.csv';
        File::ensureDirectoryExists($this->directory);
        Exporter::for(Parcel::class)->write(Exporter::output('csv', $csv), $query());
        $xlsx = $this->directory.'/e.xlsx';
        Exporter::for(Parcel::class)->write(Exporter::output('xlsx', $xlsx), $query());

        $text = (string) file_get_contents($csv);
        $this->assertStringStartsWith("\xEF\xBB\xBFID;Article;Name;Code;", $text);
        $this->assertStringContainsString("1;'+cmd;'=SUM(1+1);'@x;", $text);
        $this->assertStringContainsString(';-5;1', $text);

        $read = [];
        foreach ([$csv, $xlsx] as $path) {
            $read[] = (new SourceFile)->normalize($path, $this->directory.'/read-'.basename($path).'.txt');
            $this->assertSame(self::HEADER, $read[count($read) - 1]['headers']);
            $this->assertStringContainsString(',+cmd,=SUM(1+1),@x,', (string) file_get_contents($this->directory.'/read-'.basename($path).'.txt'));
        }

        $this->assertSame(['csv', 'xlsx'], array_column($read, 'format'));
    }

    public function test_the_text_of_a_record_is_what_the_import_gets_back(): void
    {
        Parcel::query()->create(['name' => 'Куртка «Север»', 'article' => '00123', 'detail_text' => "Line one\nLine \"two\"; with ; signs"]);
        File::ensureDirectoryExists($this->directory);

        foreach (['csv', 'xlsx'] as $format) {
            $path = $this->directory.'/e.'.$format;
            Exporter::for(Parcel::class)->write(Exporter::output($format, $path), Parcel::query());
            $read = (new SourceFile)->normalize($path, $this->directory."/{$format}.txt");

            $this->assertSame(self::HEADER, $read['headers'], $format);
            $this->assertSame(1, $read['total'], $format);

            $cells = str_getcsv(trim((string) file_get_contents($this->directory."/{$format}.txt")), ',', '"', '');
            $this->assertSame(['2', '1', '00123', 'Куртка «Север»'], array_slice($cells, 0, 4), $format);
            $this->assertSame("Line one\nLine \"two\"; with ; signs", $cells[17], $format);
        }
    }

    /**
     * @return array<int,array{query:string}>
     */
    private function selects(): array
    {
        return array_values(array_filter(DB::getQueryLog(), static fn (array $query): bool => preg_match('/^select .* from\W+parcels\W/', $query['query']) === 1));
    }

    private function sink(): Output
    {
        return new class implements Output
        {
            public function row(array $cells): void {}

            public function close(): void {}
        };
    }
}
