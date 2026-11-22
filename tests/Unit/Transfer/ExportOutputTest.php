<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Transfer;

use Atrium\Admin\Transfer\Exporter;
use Atrium\Admin\Transfer\Output\CsvOutput;
use Atrium\Admin\Transfer\Output\Output;
use Atrium\Admin\Transfer\Output\XlsxOutput;
use Atrium\Admin\Transfer\SourceFile;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The two files an export is written to: a CSV Excel opens in the right
 * encoding, and an Excel file that keeps every cell a text.
 */
final class ExportOutputTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/atrium-output-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);

        parent::tearDown();
    }

    public function test_the_formats_and_the_output_each_one_gets(): void
    {
        $this->assertSame(['csv', 'xlsx'], Exporter::FORMATS);
        $this->assertSame('csv', Exporter::CSV);
        $this->assertSame('xlsx', Exporter::XLSX);

        $csv = Exporter::output('csv', $this->directory.'/a.csv');
        $xlsx = Exporter::output('xlsx', $this->directory.'/a.xlsx');
        $csv->close();
        $xlsx->close();

        $this->assertInstanceOf(CsvOutput::class, $csv);
        $this->assertInstanceOf(XlsxOutput::class, $xlsx);
    }

    public function test_a_csv_starts_with_a_byte_order_mark_and_cells_are_split_by_a_semicolon(): void
    {
        $path = $this->directory.'/a.csv';
        $output = new CsvOutput($path);

        $this->assertSame(';', CsvOutput::DELIMITER);

        $output->row(['Name', 'Price', 'Куртка']);
        $output->row(['Parka', '1299.50', 'Тёплая']);
        $output->close();

        $this->assertSame("\xEF\xBB\xBFName;Price;Куртка\nParka;1299.50;Тёплая\n", file_get_contents($path));
    }

    public function test_a_file_with_no_row_is_the_byte_order_mark_alone(): void
    {
        $path = $this->directory.'/empty.csv';
        $output = new CsvOutput($path);
        $output->close();

        $this->assertSame("\xEF\xBB\xBF", file_get_contents($path));
    }

    public function test_a_cell_with_a_delimiter_a_quote_a_line_break_or_a_space_is_quoted_and_a_backslash_is_not_an_escape(): void
    {
        $path = $this->directory.'/quoted.csv';
        $output = new CsvOutput($path);
        $output->row(['a;b', 'say "hi"', "two\nlines", 'a b', 'plain', 'a,b', 'back\\slash', 'back\\"slash', '']);
        $output->close();

        $this->assertSame("\xEF\xBB\xBF\"a;b\";\"say \"\"hi\"\"\";\"two\nlines\";\"a b\";plain;a,b;back\\slash;\"back\\\"\"slash\";\n", file_get_contents($path));
    }

    public function test_a_cell_a_spreadsheet_would_run_gets_an_apostrophe_in_the_header_too(): void
    {
        $path = $this->directory.'/formula.csv';
        $output = new CsvOutput($path);
        $output->row(['=HEADER()', 'Name']);
        $output->row(['=SUM(1+1)', '-5']);
        $output->row(['+1 call', '@x']);
        $output->close();

        $this->assertSame("\xEF\xBB\xBF'=HEADER();Name\n'=SUM(1+1);-5\n\"'+1 call\";'@x\n", file_get_contents($path));
    }

    public function test_the_csv_is_read_back_as_it_was_written(): void
    {
        $path = $this->directory.'/back.csv';
        $output = new CsvOutput($path);
        $output->row(['Name', 'Notes']);
        $output->row(['=SUM(1+1)', "Two\nlines; \"quoted\""]);
        $output->row(['Куртка', '']);
        $output->close();

        $read = (new SourceFile)->normalize($path, $this->directory.'/read.csv');

        $this->assertSame(['csv', 'UTF-8', ';', ['Name', 'Notes'], 2], [$read['format'], $read['encoding'], $read['delimiter'], $read['headers'], $read['total']]);
        $this->assertSame("2,=SUM(1+1),\"Two\nlines; \"\"quoted\"\"\"\n3,Куртка,\n", file_get_contents($this->directory.'/read.csv'));
    }

    public function test_an_excel_file_keeps_every_cell_a_text_and_never_a_formula(): void
    {
        $path = $this->directory.'/a.xlsx';
        $output = new XlsxOutput($path);
        $output->row(['Article', 'Price', 'Name']);
        $output->row(['00123', '1299.50', '=SUM(1+1)']);
        $output->row(['5', "'=x", '']);
        $output->close();

        $this->assertSame('PK', substr((string) file_get_contents($path), 0, 2));

        $rows = $this->read($path);

        $this->assertSame(['Article', 'Price', 'Name'], $rows[0]);
        $this->assertSame(['00123', '1299.50', '=SUM(1+1)'], $rows[1]);
        $this->assertSame(['5', "'=x", ''], $rows[2]);
        $this->assertCount(3, $rows);

        $sheet = $this->sheet($path);

        $this->assertStringNotContainsString('<f>', $sheet);
        $this->assertSame(9, substr_count($sheet, 't="inlineStr"'));
        $this->assertStringContainsString('<is><t>=SUM(1+1)</t></is>', $sheet);
    }

    public function test_only_the_header_row_of_an_excel_file_is_bold(): void
    {
        $path = $this->directory.'/bold.xlsx';
        $output = new XlsxOutput($path);
        $output->row(['Name', 'Price']);
        $output->row(['Parka', '1']);
        $output->row(['Coat', '2']);
        $output->close();

        $sheet = $this->sheet($path);

        // Style 1 is the bold one: the two header cells, and none of the other four.
        $this->assertSame(2, substr_count($sheet, ' s="1" '));
        $this->assertSame(4, substr_count($sheet, ' s="0" '));
        $this->assertStringContainsString('<c r="A1" s="1" ', $sheet);
        $this->assertStringContainsString('<c r="B1" s="1" ', $sheet);
        $this->assertStringContainsString('<c r="A2" s="0" ', $sheet);
        $this->assertStringContainsString('<c r="B3" s="0" ', $sheet);
    }

    public function test_an_excel_file_is_read_back_by_the_import_without_the_apostrophe_a_csv_needs(): void
    {
        $path = $this->directory.'/back.xlsx';
        $output = new XlsxOutput($path);
        $output->row(['Name', 'Note']);
        $output->row(['=SUM(1+1)', "'=x"]);
        $output->close();

        $read = (new SourceFile)->normalize($path, $this->directory.'/read.csv');

        $this->assertSame(['xlsx', ['Name', 'Note'], 1], [$read['format'], $read['headers'], $read['total']]);
        $this->assertSame("2,=SUM(1+1),'=x\n", file_get_contents($this->directory.'/read.csv'));
    }

    public function test_the_outputs_are_outputs(): void
    {
        $this->assertInstanceOf(Output::class, new CsvOutput($this->directory.'/o.csv'));
        $this->assertInstanceOf(Output::class, new XlsxOutput($this->directory.'/o.xlsx'));
    }

    /**
     * @return array<int,array<int,string>>
     */
    private function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(static fn (mixed $value): string => (string) $value, $row->toArray());
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    private function sheet(string $path): string
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        return $xml;
    }
}
