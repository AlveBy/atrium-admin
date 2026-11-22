<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\Fixtures\ApiAisle;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Illuminate\Support\Facades\Storage;

/**
 * A file goes through the whole way a person takes it: mapped by its headers,
 * checked, then imported, with the same counts both times.
 */
final class ImportFlowTest extends TransferTestCase
{
    private const FILE = <<<'CSV'
Article;Name;Price;Active;Aisle;Post;Colors
A-1;Parka;1 299,50;yes;Europe / Day trips;Hello;black|silver
A-2;Boots;90;no;Europe;;
A-3;;10;yes;;;
A-4;Hat;5;maybe;;;
A-5;Cap;7;yes;Asia;;
CSV;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_check_counts_what_the_import_will_do_and_writes_nothing(): void
    {
        $this->world();

        $import = $this->run_($this->upload(self::FILE, ['key' => 'article']), DataImport::CHECKING);

        $this->assertSame(DataImport::CHECKED, $import->status);
        $this->assertSame([5, 2, 0, 3], [$import->processed, $import->created, $import->updated, $import->failed]);
        $this->assertSame(
            [4 => ['The name field is required.'], 5 => ['"Active": "maybe" is neither yes nor no.'], 6 => ['"Aisle": there is no "Asia".']],
            array_combine(array_column($import->errors, 'row'), array_column($import->errors, 'messages')),
        );
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_the_import_then_writes_what_the_check_counted(): void
    {
        [$trips] = $this->world();

        $check = $this->run_($this->upload(self::FILE, ['key' => 'article']), DataImport::CHECKING);
        $import = $this->run_($this->upload(self::FILE, ['key' => 'article']), DataImport::IMPORTING);

        $this->assertSame(DataImport::DONE, $import->status);
        $this->assertSame([$check->created, $check->updated, $check->failed], [$import->created, $import->updated, $import->failed]);
        $this->assertSame(['A-1', 'A-2'], Parcel::query()->orderBy('id')->pluck('article')->all());

        $parka = Parcel::query()->where('article', 'A-1')->sole();
        $this->assertSame('1299.50', $parka->price);
        $this->assertTrue($parka->active);
        $this->assertSame($trips->id, (int) $parka->aisle_id);
        $this->assertSame(['black', 'silver'], $parka->colors);
    }

    public function test_the_same_file_again_updates_instead_of_creating(): void
    {
        $this->world();

        $this->run_($this->upload(self::FILE, ['key' => 'article']), DataImport::IMPORTING);
        $again = $this->run_($this->upload(self::FILE, ['key' => 'article']), DataImport::IMPORTING);

        $this->assertSame([5, 0, 2, 3], [$again->processed, $again->created, $again->updated, $again->failed]);
        $this->assertSame(2, Parcel::query()->count());
    }

    public function test_a_small_file_is_mapped_by_its_headers_and_imported(): void
    {
        $import = $this->run_($this->upload("Article;Name\nA-1;Parka\n", ['key' => 'article']), DataImport::IMPORTING);

        $this->assertSame(DataImport::DONE, $import->status);
        $this->assertSame(['article' => 'A-1', 'name' => 'Parka'], Parcel::query()->sole()->only(['article', 'name']));
    }

    /**
     * @return array{ApiAisle}
     */
    private function world(): array
    {
        Post::query()->create(['name' => 'Hello']);
        $europe = ApiAisle::query()->create(['name' => 'Europe']);

        return [ApiAisle::query()->create(['name' => 'Day trips', 'parent_id' => $europe->id])];
    }

    /**
     * @param  array<string,mixed>  $options
     */
    private function upload(string $content, array $options): DataImport
    {
        $path = $this->directory.'-source.csv';
        file_put_contents($path, $content);

        return $this->importOf('parcels', $path, $options);
    }
}
