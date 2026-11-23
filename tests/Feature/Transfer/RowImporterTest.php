<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Tests\Fixtures\ApiAisle;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Transfer\Hosts;
use Atrium\Admin\Transfer\ImportFailed;
use Atrium\Admin\Transfer\RecordWriter;
use Atrium\Admin\Transfer\RowImporter;
use Atrium\Admin\Transfer\Target;
use Atrium\Admin\Transfer\Targets;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A row of the file becomes a record the way the form would save it; a
 * check says what the import would do and writes nothing.
 */
final class RowImporterTest extends TransferTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Storage::fake('public');
    }

    public function test_a_link_is_found_by_name_among_more_records_than_the_form_lists(): void
    {
        for ($i = 1; $i <= 60; $i++) {
            Post::query()->create(['name' => "Post {$i}"]);
        }

        $import = $this->import(['article', 'name', 'code', 'post_id']);
        $this->importer($import)->import(2, ['A-1', 'Parka', 'parka', 'post 57']);

        $this->assertSame([], $import->errors);
        $this->assertSame(Post::query()->where('name', 'Post 57')->value('id'), Parcel::query()->sole()->post_id);
    }

    public function test_a_row_creates_a_record_with_every_kind_of_field(): void
    {
        $post = Post::query()->create(['name' => 'Hello']);
        $europe = ApiAisle::query()->create(['name' => 'Europe']);
        $trips = ApiAisle::query()->create(['name' => 'Day trips', 'parent_id' => $europe->id]);
        Storage::disk('public')->put('catalog/1.jpg', 'x');
        Storage::disk('public')->put('catalog/2.jpg', 'x');
        Storage::disk('public')->put('catalog/3.jpg', 'x');

        $import = $this->import(['article', 'name', 'code', 'price', 'active', 'booking', 'starts_at', 'starts_on', 'label', 'colors', 'features', 'picture', 'photos', 'aisle_id', 'post_id', 'detail_text', 'sort']);
        $this->importer($import)->import(2, ['A-1', 'Parka', 'parka', '1 299,50', 'нет', 'да', '05.03.2026 10:30', '05.03.2026', 'Bestseller', 'black | Silver', "Warm\nLight", 'catalog/1.jpg', 'catalog/2.jpg, catalog/3.jpg', 'europe / day trips', 'hello', "Two\nlines", '10']);

        $this->assertSame([1, 1, 0, 0, 0], [$import->processed, $import->created, $import->updated, $import->failed, $import->skipped]);
        $this->assertSame([], $import->errors);

        $parcel = Parcel::query()->sole();
        $this->assertSame('A-1', $parcel->article);
        $this->assertSame(['en' => 'Parka'], $parcel->getTranslations('name'));
        $this->assertSame('parka', $parcel->code);
        $this->assertSame('1299.50', $parcel->price);
        $this->assertFalse($parcel->active);
        $this->assertSame('YES', $parcel->booking);
        $this->assertSame('2026-03-05 10:30:00', $parcel->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-05', $parcel->starts_on?->format('Y-m-d'));
        $this->assertSame('hit', $parcel->label);
        $this->assertSame(['black', 'silver'], $parcel->colors);
        $this->assertSame(['Warm', 'Light'], $parcel->features);
        $this->assertSame('catalog/1.jpg', $parcel->picture);
        $this->assertSame(['catalog/2.jpg', 'catalog/3.jpg'], $parcel->photos);
        $this->assertSame($trips->id, (int) $parcel->aisle_id);
        $this->assertSame($post->id, (int) $parcel->post_id);
        $this->assertSame("Two\nlines", $parcel->detail_text);
        $this->assertSame(10, $parcel->sort);
    }

    public function test_a_row_updates_the_record_with_its_key_and_leaves_the_rest(): void
    {
        $parcel = $this->parcel(['article' => 'A-1', 'price' => 100, 'detail_text' => 'Box']);

        $import = $this->import(['article', 'name', 'price', null, 'detail_text']);
        $this->importer($import)->import(2, ['A-1', '', '150', 'ignored', '']);

        $this->assertSame([1, 0, 1], [$import->processed, $import->created, $import->updated]);
        $parcel->refresh();
        $this->assertSame('Parcel', $parcel->name);
        $this->assertSame('150.00', $parcel->price);
        $this->assertSame('Box', $parcel->detail_text);
        $this->assertSame(1, Parcel::query()->count());
    }

    public function test_a_row_that_updates_changes_only_the_columns_of_the_file(): void
    {
        $parcel = $this->parcel(['article' => 'A-1', 'code' => 'old', 'label' => 'hit', 'sort' => 7, 'active' => true]);

        $import = $this->import(['article', 'code']);
        $this->importer($import)->import(2, ['A-1', 'new']);

        $parcel->refresh();
        $this->assertSame([[], 'new', 'hit', 7, true], [$import->errors, $parcel->code, $parcel->label, $parcel->sort, $parcel->active]);
    }

    public function test_a_short_row_leaves_the_missing_cells_empty(): void
    {
        $parcel = $this->parcel(['article' => 'A-1', 'price' => 100]);

        $import = $this->import(['article', 'price']);
        $this->importer($import)->import(2, ['A-1']);

        $this->assertSame(1, $import->updated);
        $this->assertSame('100.00', $parcel->refresh()->price);
    }

    public function test_the_first_of_two_records_with_the_same_key_is_updated(): void
    {
        $first = $this->parcel(['article' => 'A-1', 'code' => 'first']);
        $second = $this->parcel(['article' => 'A-1', 'code' => 'second']);

        $import = $this->import(['article', 'price']);
        $this->importer($import)->import(2, ['A-1', '5']);

        $this->assertSame('5.00', $first->refresh()->price);
        $this->assertNull($second->refresh()->price);
    }

    public function test_records_are_found_by_the_key_the_import_names(): void
    {
        $byCode = $this->parcel(['code' => 'parka', 'article' => 'X']);
        $byArticle = $this->parcel(['code' => 'boots', 'article' => '1c-7']);

        $import = $this->import(['code', 'article', 'price'], ['key' => 'code']);
        $this->importer($import)->import(2, ['parka', '', '7']);
        $this->assertSame('7.00', $byCode->refresh()->price);

        $import = $this->import(['code', 'article', 'price'], ['key' => 'article']);
        $this->importer($import)->import(2, ['', '1c-7', '8']);
        $this->assertSame('8.00', $byArticle->refresh()->price);

        $import = $this->import(['id', 'price'], ['key' => 'id']);
        $this->importer($import)->import(2, [(string) $byCode->id, '9']);
        $this->assertSame('9.00', $byCode->refresh()->price);
        $this->assertSame('8.00', $byArticle->refresh()->price);
        $this->assertSame(2, Parcel::query()->count());
    }

    public function test_the_key_is_the_one_the_import_names_not_the_first_column(): void
    {
        $this->parcel(['code' => 'parka', 'article' => 'A-1']);

        $import = $this->import(['article', 'code'], ['key' => 'code']);
        $this->importer($import)->import(2, ['A-2', 'parka']);

        $this->assertSame([0, 1, []], [$import->created, $import->updated, $import->errors]);
        $this->assertSame('A-2', Parcel::query()->sole()->article);
    }

    public function test_the_id_finds_a_record_and_is_never_written(): void
    {
        $existing = $this->parcel(['article' => 'A-1']);
        $missing = $existing->id + 1000;

        $import = $this->import(['id', 'article', 'name'], ['key' => 'id']);
        $importer = $this->importer($import);
        $importer->import(2, [(string) $existing->id, 'A-2', 'Parka']);
        $importer->import(3, [(string) $missing, 'A-3', 'Boots']);

        $this->assertSame([2, 1, 1, []], [$import->processed, $import->created, $import->updated, $import->errors]);
        $this->assertSame(['A-2', 'A-3'], Parcel::query()->orderBy('id')->pluck('article')->all());
        $ids = Parcel::query()->orderBy('id')->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->assertSame($existing->id, $ids[0]);
        $this->assertGreaterThan($existing->id, $ids[1]);
        $this->assertLessThan($missing, $ids[1]);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function notIds(): array
    {
        return ['zero' => ['0'], 'negative' => ['-1'], 'text' => ['abc'], 'a fraction' => ['1.5'], 'a leading zero' => ['01'], 'a space' => ['1 2'], 'a trailing line break' => ["1\n"]];
    }

    #[DataProvider('notIds')]
    public function test_something_that_is_not_an_id_fails_the_row(string $value): void
    {
        $import = $this->import(['id', 'article'], ['key' => 'id']);
        $this->importer($import)->import(2, [$value, 'A-1']);

        $this->assertSame([1, 1, 0], [$import->processed, $import->failed, $import->created]);
        $this->assertSame([['row' => 2, 'skipped' => false, 'messages' => ['"'.$value.'" is not an id: it must be a whole number above zero.']]], $import->errors);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_a_row_without_the_key_fails(): void
    {
        $import = $this->import(['article', 'name']);
        $this->importer($import)->import(5, ['', 'Parka']);

        $this->assertSame([1, 1, 0], [$import->processed, $import->failed, $import->created]);
        $this->assertSame([['row' => 5, 'skipped' => false, 'messages' => ['"Article" is empty: the record cannot be found or created.']]], $import->errors);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_a_row_whose_key_has_no_column_fails(): void
    {
        $import = $this->import(['name'], ['key' => 'code']);
        $this->importer($import)->import(3, ['Parka']);

        $this->assertSame([['row' => 3, 'skipped' => false, 'messages' => ['"Code" is empty: the record cannot be found or created.']]], $import->errors);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_a_key_the_module_has_no_field_for_cannot_start(): void
    {
        $this->expectExceptionObject(ImportFailed::because('key', ['key' => 'ghost']));

        $this->importer($this->import(['article'], ['key' => 'ghost']));
    }

    public function test_an_import_without_a_key_cannot_start(): void
    {
        $import = $this->import(['article']);
        $import->options = ['mode' => 'both'];

        $this->expectExceptionObject(ImportFailed::because('key', ['key' => '']));

        $this->importer($import);
    }

    public function test_create_only_skips_a_record_that_is_there_and_creates_a_new_one(): void
    {
        $parcel = $this->parcel(['article' => 'A-1', 'price' => 100]);

        $import = $this->import(['article', 'name', 'price'], ['mode' => 'create']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', '150']);
        $importer->import(3, ['A-2', 'Boots', '90']);

        $this->assertSame([2, 1, 0, 1], [$import->processed, $import->created, $import->updated, $import->skipped]);
        $this->assertSame([['row' => 2, 'skipped' => true, 'messages' => ['The record "A-1" is already there, and only new records are created.']]], $import->errors);
        $this->assertSame('100.00', $parcel->refresh()->price);
        $this->assertTrue(Parcel::query()->where('article', 'A-2')->exists());
    }

    public function test_update_only_skips_a_new_record_and_updates_one_that_is_there(): void
    {
        $parcel = $this->parcel(['article' => 'A-1', 'price' => 100]);

        $import = $this->import(['article', 'name', 'price'], ['mode' => 'update']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-2', 'Boots', '90']);
        $importer->import(3, ['A-1', 'Parka', '150']);

        $this->assertSame([2, 0, 1, 1], [$import->processed, $import->created, $import->updated, $import->skipped]);
        $this->assertSame([['row' => 2, 'skipped' => true, 'messages' => ['There is no record "A-2", and only existing records are updated.']]], $import->errors);
        $this->assertSame('150.00', $parcel->refresh()->price);
        $this->assertSame(1, Parcel::query()->count());
    }

    public function test_both_creates_and_updates(): void
    {
        $this->parcel(['article' => 'A-1', 'price' => 100]);

        $import = $this->import(['article', 'name', 'price'], ['mode' => 'both']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', '150']);
        $importer->import(3, ['A-2', 'Boots', '90']);

        $this->assertSame([2, 1, 1, 0, []], [$import->processed, $import->created, $import->updated, $import->skipped, $import->errors]);
    }

    public function test_an_import_without_a_mode_creates_and_updates(): void
    {
        $this->parcel(['article' => 'A-1']);

        $import = $this->import(['article', 'name']);
        $import->options = ['key' => 'article'];
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka']);
        $importer->import(3, ['A-2', 'Boots']);

        $this->assertSame([1, 1, 0], [$import->updated, $import->created, $import->skipped]);
    }

    public function test_an_import_does_not_count_on_what_a_check_remembered(): void
    {
        $import = $this->import(['article', 'name'], ['mode' => 'update']);
        $import->remember('record', 'A-1');
        $this->importer($import)->import(2, ['A-1', 'Parka']);

        $this->assertSame(1, $import->skipped);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_a_row_the_form_would_refuse_fails_with_the_reasons(): void
    {
        $import = $this->import(['article', 'price']);
        $this->importer($import)->import(2, ['A-1', '12']);

        $this->assertSame([1, 1, 0], [$import->processed, $import->failed, $import->created]);
        $this->assertSame(['The name field is required.'], $import->errors[0]['messages']);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_every_reason_the_form_gives_is_listed(): void
    {
        $import = $this->import(['article', 'price']);
        $this->importer($import)->import(2, [str_repeat('a', 41), 'cheap']);

        $this->assertSame(
            ['The article field must not be greater than 40 characters.', 'The name field is required.', 'The price field must be a number.'],
            $import->errors[0]['messages'],
        );
    }

    public function test_a_row_the_database_refuses_fails_and_is_logged(): void
    {
        Exceptions::fake();
        Parcel::saving(static fn () => throw new QueryException('testing', 'insert', [], new Exception('Deadlock')));

        $import = $this->import(['article', 'name']);
        $this->importer($import)->import(2, ['A-1', 'Parka']);

        $this->assertSame([1, 1], [$import->processed, $import->failed]);
        $this->assertSame(['The database refused the row. The error has been logged.'], $import->errors[0]['messages']);
        Exceptions::assertReported(QueryException::class);
    }

    public function test_a_failed_row_does_not_stop_the_next_one(): void
    {
        $import = $this->import(['article', 'name']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', '']);
        $importer->import(3, ['A-2', 'Boots']);

        $this->assertSame([2, 1, 1], [$import->processed, $import->failed, $import->created]);
        $this->assertSame(['A-2'], Parcel::query()->pluck('article')->all());
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function flags(): array
    {
        return ['yes' => ['yes', true], 'Russian no' => ['нет', false], 'one' => ['1', true], 'zero' => ['0', false]];
    }

    #[DataProvider('flags')]
    public function test_a_checkbox_reads_yes_and_no(string $cell, bool $expected): void
    {
        $import = $this->import(['article', 'name', 'active']);
        $this->importer($import)->import(2, ['A-1', 'Parka', $cell]);

        $this->assertSame([], $import->errors);
        $this->assertSame($expected, Parcel::query()->sole()->active);
    }

    public function test_a_value_that_is_neither_yes_nor_no_fails(): void
    {
        $import = $this->import(['article', 'name', 'active']);
        $this->importer($import)->import(2, ['A-1', 'Parka', 'maybe']);

        $this->assertSame(['"Active": "maybe" is neither yes nor no.'], $import->errors[0]['messages']);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_a_checkbox_that_keeps_a_word_keeps_it_for_yes_and_nothing_for_no(): void
    {
        $import = $this->import(['article', 'name', 'booking']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'да']);
        $importer->import(3, ['A-2', 'Boots', 'no']);

        $this->assertSame([], $import->errors);
        $this->assertSame('YES', Parcel::query()->where('article', 'A-1')->value('booking'));
        $this->assertContains(Parcel::query()->where('article', 'A-2')->value('booking'), [null, '']);
    }

    public function test_an_option_is_found_by_value_or_by_label(): void
    {
        $import = $this->import(['article', 'name', 'label']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'new']);
        $importer->import(3, ['A-2', 'Boots', 'BESTSELLER!']);
        $importer->import(4, ['A-3', 'Hat', ' NEW ']);

        $this->assertSame([], $import->errors);
        $this->assertSame('new', Parcel::query()->where('article', 'A-1')->value('label'));
        $this->assertSame('hit', Parcel::query()->where('article', 'A-2')->value('label'));
        $this->assertSame('new', Parcel::query()->where('article', 'A-3')->value('label'));
    }

    public function test_several_options_are_found_by_value_or_label_too(): void
    {
        $import = $this->import(['article', 'name', 'colors']);
        $this->importer($import)->import(2, ['A-1', 'Parka', "Black|SILVER\nblack"]);

        $this->assertSame([], $import->errors);
        $this->assertSame(['black', 'silver', 'black'], Parcel::query()->sole()->colors);
    }

    public function test_an_option_that_is_not_there_fails(): void
    {
        $import = $this->import(['article', 'name', 'label', 'colors']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'Cheap', '']);
        $importer->import(3, ['A-2', 'Boots', '', 'black|Green']);

        $this->assertSame(['"Label": there is no option "Cheap".'], $import->errors[0]['messages']);
        $this->assertSame(['"Colours": there is no option "Green".'], $import->errors[1]['messages']);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_an_option_named_twice_fails_unless_the_value_says_which(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Parcel::class ? [
            ...array_filter($fields, static fn ($field): bool => ! in_array($field->getName(), ['label', 'colors'], true)),
            SelectField::make('label')->label('Label')->options(['1' => 'Box', '2' => 'box', '3' => '1']),
            MultiSelectField::make('colors')->label('Colours')->options(['red' => 'Red', 'crimson' => 'red']),
        ] : $fields);

        $import = $this->import(['article', 'name', 'label', 'colors']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'Box', '']);
        $importer->import(3, ['A-2', 'Boots', '', 'Red']);
        $importer->import(4, ['A-3', 'Hat', '1', 'crimson']);

        $this->assertSame(['"Label": more than one option is called "Box".'], $import->errors[0]['messages']);
        $this->assertSame(['"Colours": more than one option is called "Red".'], $import->errors[1]['messages']);
        $this->assertSame('1', Parcel::query()->where('article', 'A-3')->value('label'));
        $this->assertSame(['crimson'], Parcel::query()->where('article', 'A-3')->sole()->colors);
    }

    public function test_a_post_is_found_by_its_id_or_name(): void
    {
        $post = Post::query()->create(['name' => 'Hello']);

        $import = $this->import(['article', 'name', 'post_id']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', (string) $post->id]);
        $importer->import(3, ['A-2', 'Boots', 'hello']);
        $importer->import(4, ['A-3', 'Hat', 'Nobody']);

        $this->assertSame($post->id, (int) Parcel::query()->where('article', 'A-1')->value('post_id'));
        $this->assertSame($post->id, (int) Parcel::query()->where('article', 'A-2')->value('post_id'));
        $this->assertSame([['row' => 4, 'skipped' => false, 'messages' => ['"Post": there is no option "Nobody".']]], $import->errors);
    }

    public function test_the_options_of_a_field_are_read_once_per_import(): void
    {
        Post::query()->create(['name' => 'Hello']);
        $import = $this->import(['article', 'name', 'post_id']);
        $importer = $this->importer($import);
        $lists = static function (Closure $import): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $import();

            return count(array_filter(DB::getQueryLog(), static fn (array $query): bool => preg_match('/^select \* from ["`]posts["`]$/', $query['query']) === 1));
        };

        $first = $lists(static fn () => $importer->import(2, ['A-1', 'Parka', 'Hello']));
        $second = $lists(static fn () => $importer->import(3, ['A-2', 'Boots', 'hello']));

        $this->assertSame(1, $first - $second);
        $this->assertSame(2, Parcel::query()->whereNotNull('post_id')->count());
    }

    public function test_dates_are_read_day_first_and_a_date_drops_the_time(): void
    {
        $import = $this->import(['article', 'name', 'starts_at', 'starts_on']);
        $this->importer($import)->import(2, ['A-1', 'Parka', '05/03/2026 10:30', '05/03/2026 10:30']);

        $this->assertSame([], $import->errors);
        $this->assertSame('2026-03-05 10:30:00', Parcel::query()->sole()->starts_at?->format('Y-m-d H:i:s'));
        // SQLite keeps what it is given; MySQL and PostgreSQL keep the date alone.
        $this->assertContains(DB::table('parcels')->value('starts_on'), ['2026-03-05 00:00:00', '2026-03-05']);
    }

    public function test_a_date_that_is_no_date_fails_with_the_reason_of_the_form(): void
    {
        $import = $this->import(['article', 'name', 'starts_on']);
        $this->importer($import)->import(2, ['A-1', 'Parka', 'next week']);

        $this->assertSame(1, $import->failed);
        $this->assertSame(['The starts on field must be a valid date.'], $import->errors[0]['messages']);
    }

    public function test_a_list_cell_becomes_a_list(): void
    {
        $import = $this->import(['article', 'name', 'features']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', "Warm | Light\n\nWaterproof"]);
        $importer->import(3, ['A-2', 'Boots', 'One']);

        $this->assertSame(['Warm', 'Light', 'Waterproof'], Parcel::query()->where('article', 'A-1')->sole()->features);
        $this->assertSame(['One'], Parcel::query()->where('article', 'A-2')->sole()->features);
    }

    public function test_a_number_is_read_the_way_price_lists_write_it(): void
    {
        $import = $this->import(['article', 'name', 'price', 'sort']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', "1\u{00A0}299,5", '3']);
        $importer->import(3, ['A-2', 'Boots', '1,299.50', '4']);

        $this->assertSame([], $import->errors);
        $this->assertSame(['1299.50', '1299.50'], Parcel::query()->orderBy('id')->pluck('price')->all());
        $this->assertSame([3, 4], Parcel::query()->orderBy('id')->pluck('sort')->all());
    }

    public function test_a_target_that_is_gone_leaves_its_column_out(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => array_values(array_filter(
            $fields,
            static fn ($field): bool => $field->getName() !== 'price',
        )));

        $import = $this->import(['article', 'name', 'price', 'nonsense']);
        $this->importer($import)->import(2, ['A-1', 'Parka', '150', 'x']);

        $this->assertSame([[], 1], [$import->errors, $import->created]);
        $this->assertNull(Parcel::query()->sole()->price);
    }

    public function test_an_aisle_is_found_by_name_or_by_path_case_aside(): void
    {
        [$europe, $trips] = $this->aisles();

        $import = $this->import(['article', 'name', 'aisle_id']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'EUROPE']);
        $importer->import(3, ['A-2', 'Boots', ' day trips ']);
        $importer->import(4, ['A-3', 'Hat', 'europe/Day Trips']);
        $importer->import(5, ['A-4', 'Cap', 'Europe / Day trips /']);

        $this->assertSame([], $import->errors);
        $this->assertSame([$europe->id, $trips->id, $trips->id, $trips->id], Parcel::query()->orderBy('id')->pluck('aisle_id')->map(static fn ($id): int => (int) $id)->all());
    }

    public function test_the_separator_of_a_path_is_an_option(): void
    {
        [, $trips] = $this->aisles();

        $import = $this->import(['article', 'name', 'aisle_id'], ['separator' => '>']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'Europe > Day trips']);
        $importer->import(3, ['A-2', 'Boots', 'Europe / Day trips']);

        $this->assertSame($trips->id, (int) Parcel::query()->where('article', 'A-1')->value('aisle_id'));
        $this->assertSame(1, $import->failed);
        $this->assertSame(['"Aisle": there is no "Europe / Day trips".'], $import->errors[0]['messages']);
    }

    public function test_an_aisle_that_is_not_there_fails(): void
    {
        $this->aisles();

        $import = $this->import(['article', 'name', 'aisle_id']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'Asia']);
        $importer->import(3, ['A-2', 'Boots', 'Europe / Asia']);
        $importer->import(4, ['A-3', 'Hat', ' / ']);

        $this->assertSame(
            [['"Aisle": there is no "Asia".'], ['"Aisle": there is no "Europe / Asia".'], ['"Aisle": there is no " / ".']],
            array_column($import->errors, 'messages'),
        );
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_an_aisle_named_in_two_places_fails_unless_one_is_at_the_top_or_the_path_says_which(): void
    {
        [$europe, $trips] = $this->aisles();
        $asia = ApiAisle::query()->create(['name' => 'Asia']);
        $other = ApiAisle::query()->create(['name' => 'Day trips', 'parent_id' => $asia->id]);
        ApiAisle::query()->create(['name' => 'Tours', 'parent_id' => $europe->id]);
        ApiAisle::query()->create(['name' => 'Tours', 'parent_id' => $asia->id]);
        ApiAisle::query()->create(['name' => 'Asia', 'parent_id' => $europe->id]);

        $import = $this->import(['article', 'name', 'aisle_id']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'Day trips']);
        $importer->import(3, ['A-2', 'Boots', 'Asia / Day trips']);
        $importer->import(4, ['A-3', 'Hat', 'Europe / Day trips']);
        $importer->import(5, ['A-4', 'Cap', 'Tours']);
        $importer->import(6, ['A-5', 'Hood', 'Asia']);

        $this->assertSame(
            [['"Aisle": more than one is called "Day trips": write its path, "Parent / Day trips".'], ['"Aisle": more than one is called "Tours": write its path, "Parent / Tours".']],
            array_column($import->errors, 'messages'),
        );
        $this->assertSame([2, 5], array_column($import->errors, 'row'));
        $this->assertSame($other->id, (int) Parcel::query()->where('article', 'A-2')->value('aisle_id'));
        $this->assertSame($trips->id, (int) Parcel::query()->where('article', 'A-3')->value('aisle_id'));
        $this->assertSame($asia->id, (int) Parcel::query()->where('article', 'A-5')->value('aisle_id'));
    }

    public function test_an_image_that_is_not_there_fails_the_row(): void
    {
        $import = $this->import(['article', 'name', 'picture', 'photos']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'catalog/none.jpg', '']);
        $importer->import(3, ['A-2', 'Boots', '', 'catalog/none.jpg']);

        $this->assertSame(['There is no image catalog/none.jpg on the site.'], $import->errors[0]['messages']);
        $this->assertSame(['There is no image catalog/none.jpg on the site.'], $import->errors[1]['messages']);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_one_bad_picture_in_a_gallery_fails_the_row(): void
    {
        Storage::disk('public')->put('catalog/1.jpg', 'x');

        $import = $this->import(['article', 'name', 'photos']);
        $this->importer($import)->import(2, ['A-1', 'Parka', 'catalog/1.jpg | ../secret.jpg']);

        $this->assertSame(['The image path is not allowed: ../secret.jpg'], $import->errors[0]['messages']);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_an_image_url_is_downloaded_on_import(): void
    {
        Http::fake(['example.com/*' => Http::response(base64_decode(self::GIF))]);

        $import = $this->import(['article', 'name', 'picture']);
        $this->importer($import, public: true)->import(2, ['A-1', 'Parka', 'https://example.com/1.gif']);

        $this->assertSame([], $import->errors);
        $this->assertStringStartsWith('/storage/import/', Parcel::query()->sole()->picture);
        Http::assertSentCount(1);
    }

    public function test_the_pictures_of_a_gallery_are_downloaded_one_by_one(): void
    {
        Http::fake(['example.com/*' => Http::response(base64_decode(self::GIF))]);

        $import = $this->import(['article', 'name', 'photos']);
        $this->importer($import, public: true)->import(2, ['A-1', 'Parka', 'https://example.com/1.gif, https://example.com/2.gif']);

        $photos = Parcel::query()->sole()->photos;
        $this->assertCount(2, $photos);
        $this->assertNotSame($photos[0], $photos[1]);
        Http::assertSentCount(2);
    }

    public function test_an_image_on_an_address_inside_the_network_fails_the_row(): void
    {
        $import = $this->import(['article', 'name', 'picture']);
        $this->importer($import)->import(2, ['A-1', 'Parka', 'https://example.com/1.gif']);

        $this->assertSame(['The image is on an address inside the network, which is not fetched: https://example.com/1.gif'], $import->errors[0]['messages']);
        Http::assertNothingSent();
    }

    public function test_a_check_writes_nothing_and_counts_what_the_import_would_do(): void
    {
        $this->parcel(['article' => 'A-1', 'price' => 100]);

        $import = $this->import(['article', 'name', 'price', 'picture'], [], DataImport::CHECKING);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', '', '150', '']);
        $importer->import(3, ['A-2', 'Boots', '90', 'https://example.com/1.gif']);
        $importer->import(4, ['a-2', 'Boots', '95', '']);
        $importer->import(5, ['A-3', '', '10', '']);
        $importer->import(6, ['A-3', 'Hat', '10', '']);

        $this->assertSame([5, 2, 2, 1, 0], [$import->processed, $import->created, $import->updated, $import->failed, $import->skipped]);
        $this->assertSame([['row' => 5, 'skipped' => false, 'messages' => ['The name field is required.']]], $import->errors);
        $this->assertSame('100.00', Parcel::query()->sole()->price);
        $this->assertSame(1, Parcel::query()->count());
        Http::assertNothingSent();
    }

    public function test_a_check_notes_the_keys_it_has_met(): void
    {
        $import = $this->import(['article', 'name'], [], DataImport::CHECKING);
        $this->importer($import)->import(2, ['A-1', 'Parka']);

        $this->assertTrue($import->seen('record', 'a-1'));
        $this->assertFalse($import->seen('record', 'A-2'));
    }

    public function test_a_check_does_not_note_a_row_that_failed_or_was_skipped(): void
    {
        $this->parcel(['article' => 'A-9']);
        $import = $this->import(['article', 'name'], ['mode' => 'create'], DataImport::CHECKING);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', '']);
        $importer->import(3, ['A-9', 'Parka']);

        $this->assertFalse($import->seen('record', 'A-1'));
        $this->assertFalse($import->seen('record', 'A-9'));
    }

    public function test_an_import_notes_nothing(): void
    {
        $import = $this->import(['article', 'name']);
        $this->importer($import)->import(2, ['A-1', 'Parka']);

        $this->assertFalse($import->seen('record', 'A-1'));
    }

    public function test_a_check_skips_what_the_mode_would_skip_further_down_the_file(): void
    {
        $import = $this->import(['article', 'name'], ['mode' => 'create'], DataImport::CHECKING);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka']);
        $importer->import(3, ['A-1', 'Parka again']);

        $this->assertSame([1, 1], [$import->created, $import->skipped]);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_a_check_of_an_update_only_import_skips_a_new_record_met_twice(): void
    {
        $import = $this->import(['article', 'name'], ['mode' => 'update'], DataImport::CHECKING);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka']);
        $importer->import(3, ['A-1', 'Parka again']);

        $this->assertSame([0, 0, 2], [$import->created, $import->updated, $import->skipped]);
    }

    public function test_a_check_finds_the_reasons_the_form_would_give(): void
    {
        $this->parcel(['article' => 'A-1']);

        $import = $this->import(['article', 'name', 'price'], [], DataImport::CHECKING);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', '', 'cheap']);
        $importer->import(3, ['A-2', 'Boots', '']);

        $this->assertSame(['The price field must be a number.'], $import->errors[0]['messages']);
        $this->assertSame([0, 1, 1], [$import->updated, $import->created, $import->failed]);
    }

    public function test_a_check_of_an_update_asks_for_the_columns_of_the_file_only(): void
    {
        $this->parcel(['article' => 'A-1']);

        $import = $this->import(['article', 'price'], [], DataImport::CHECKING);
        $this->importer($import)->import(2, ['A-1', '12']);

        $this->assertSame([[], 1], [$import->errors, $import->updated]);
    }

    public function test_a_check_refuses_an_image_address_that_is_not_one(): void
    {
        $import = $this->import(['article', 'name', 'picture'], [], DataImport::CHECKING);
        $this->importer($import)->import(2, ['A-1', 'Parka', 'http://']);

        $this->assertSame(['The image address is not valid: http://'], $import->errors[0]['messages']);
    }

    public function test_a_check_looks_up_an_aisle_but_writes_none(): void
    {
        $this->aisles();

        $import = $this->import(['article', 'name', 'aisle_id'], [], DataImport::CHECKING);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'Europe / Day trips']);
        $importer->import(3, ['A-2', 'Boots', 'Asia']);

        $this->assertSame([1, 1], [$import->created, $import->failed]);
        $this->assertSame(['"Aisle": there is no "Asia".'], $import->errors[0]['messages']);
        $this->assertSame(2, ApiAisle::query()->count());
    }

    public function test_a_check_takes_a_field_kept_per_language_from_its_one_column(): void
    {
        $import = $this->import(['article', 'name'], [], DataImport::CHECKING);

        $this->importer($import)->import(2, ['A-1', 'Parka']);

        $this->assertSame([[], 1], [$import->errors, $import->created]);
    }

    public function test_a_field_kept_per_language_takes_its_language_from_the_column(): void
    {
        $this->addRussian();

        $import = $this->import(['article', 'name.ru'], [], DataImport::CHECKING);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Парка']);

        $import->mapping = ['article', 'name.en', 'name.ru'];
        $importer->import(3, ['A-2', 'Parka', 'Парка']);

        $this->assertSame(['The name (English) field is required.'], $import->errors[0]['messages']);
        $this->assertSame([1, 1], [$import->failed, $import->created]);
    }

    public function test_every_language_of_a_field_is_saved_and_a_language_alone_updates_that_language(): void
    {
        $this->addRussian();

        $import = $this->import(['article', 'name.en', 'name.ru']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'Парка']);

        $this->assertSame(['en' => 'Parka', 'ru' => 'Парка'], Parcel::query()->sole()->getTranslations('name'));

        $update = $this->import(['article', 'name.ru']);
        $this->importer($update)->import(2, ['A-1', 'Куртка']);

        $this->assertSame([[], ['en' => 'Parka', 'ru' => 'Куртка']], [$update->errors, Parcel::query()->sole()->getTranslations('name')]);
    }

    public function test_a_record_is_found_by_a_key_kept_per_language_in_the_default_language(): void
    {
        $this->addRussian();
        $this->resources(Tale::class);
        $tale = Tale::query()->create(['name' => ['en' => 'Tale', 'ru' => 'Сказка'], 'code' => ['en' => 'tale', 'ru' => 'skazka']]);

        $import = $this->import(['code.ru', 'code.en', 'name.ru', 'name.en'], ['key' => 'code'], module: 'tales');
        $importer = $this->importer($import, model: Tale::class);
        $importer->import(2, ['skazka', 'tale', 'Новая', 'New']);
        $importer->import(3, ['x', 'other', 'Другая', 'Other']);

        $this->assertSame([[], 1, 1], [$import->errors, $import->updated, $import->created]);
        $this->assertSame('Новая', $tale->refresh()->getTranslation('name', 'ru'));
        $this->assertSame(2, Tale::query()->count());
    }

    public function test_a_record_is_found_by_a_key_of_one_language_as_it_is(): void
    {
        $this->resources(Tale::class);
        $tale = Tale::query()->create(['name' => ['en' => 'Tale'], 'code' => ['en' => 'tale']]);

        $import = $this->import(['code', 'name'], ['key' => 'code'], module: 'tales');
        $this->importer($import, model: Tale::class)->import(2, ['tale', 'Renamed']);

        $this->assertSame([[], 1, 0], [$import->errors, $import->updated, $import->created]);
        $this->assertSame('Renamed', $tale->refresh()->name);
    }

    public function test_a_module_reads_a_cell_its_own_way_and_a_check_is_told_so(): void
    {
        $seen = [];
        Hook::filter('atrium.import.targets', static function (array $targets, string $model) use (&$seen): array {
            $targets['shout'] = new Target('shout', 'Shout', resolve: static function (string $cell, DataImport $import, bool $check) use (&$seen): array {
                $seen[] = [$cell, $import->module, $check];

                return ['code' => strtoupper($cell), 'label' => 'new'];
            });

            return $targets;
        });

        $import = $this->import(['article', 'name', 'shout']);
        $importer = $this->importer($import);
        $importer->import(2, ['A-1', 'Parka', 'hey']);
        $check = $this->import(['article', 'name', 'shout'], [], DataImport::CHECKING);
        $this->importer($check)->import(2, ['A-2', 'Boots', 'ho']);

        $this->assertSame([[], [], 1, 1], [$import->errors, $check->errors, $import->created, $check->created]);
        $this->assertSame([['hey', 'parcels', false], ['ho', 'parcels', true]], $seen);
        $parcel = Parcel::query()->sole();
        $this->assertSame(['HEY', 'new'], [$parcel->code, $parcel->label]);
    }

    public function test_what_a_module_resolves_is_validated_like_the_rest(): void
    {
        Hook::filter('atrium.import.targets', static function (array $targets): array {
            $targets['shout'] = new Target('shout', 'Shout', resolve: static fn (string $cell): array => ['label' => 'nonsense']);

            return $targets;
        });

        $import = $this->import(['article', 'name', 'shout']);
        $this->importer($import)->import(2, ['A-1', 'Parka', 'hey']);

        $this->assertSame(1, $import->failed);
        $this->assertSame(['The selected label is invalid.'], $import->errors[0]['messages']);
    }

    public function test_a_module_may_take_over_the_reading_of_a_field_of_the_form(): void
    {
        Hook::filter('atrium.import.targets', static function (array $targets): array {
            $targets['code'] = new Target('code', 'Code', $targets['code']->field, resolve: static fn (string $cell): array => ['code' => 'x-'.$cell]);

            return $targets;
        });

        $import = $this->import(['article', 'name', 'code']);
        $this->importer($import)->import(2, ['A-1', 'Parka', 'one']);

        $this->assertSame('x-one', Parcel::query()->sole()->code);
    }

    public const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * @return array{ApiAisle,ApiAisle}
     */
    private function aisles(): array
    {
        $europe = ApiAisle::query()->create(['name' => 'Europe']);

        return [$europe, ApiAisle::query()->create(['name' => 'Day trips', 'parent_id' => $europe->id])];
    }

    private function addRussian(): void
    {
        Language::query()->create(['code' => 'ru', 'name' => 'Русский', 'active' => true, 'is_default' => false, 'sort' => 2]);
        app(ContentLanguages::class)->flush();
    }

    /**
     * @param  class-string<Model>  ...$models
     */
    private function resources(string ...$models): void
    {
        config(['atrium.resources' => [Parcel::class, Post::class, ...$models]]);
        app()->forgetInstance(ResourceRegistry::class);
    }

    /**
     * @param  array<int,string|null>  $mapping
     * @param  array<string,mixed>  $options
     */
    private function import(array $mapping, array $options = [], string $status = DataImport::IMPORTING, string $module = 'parcels'): DataImport
    {
        $import = DataImport::query()->create([
            'module' => $module, 'status' => DataImport::NEW, 'original_name' => 'a.csv', 'file' => 'a.csv', 'format' => 'csv',
            'encoding' => 'UTF-8', 'delimiter' => ';', 'headers' => array_map(strval(...), array_keys($mapping)), 'total' => 1,
            'mapping' => $mapping,
            'options' => ['key' => 'article', 'mode' => 'both', ...$options],
        ]);
        $import->begin($status);

        return $import;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function importer(DataImport $import, string $model = Parcel::class, bool $public = false): RowImporter
    {
        return new RowImporter($import, $model, Targets::for($model), new RecordWriter($model), new Hosts(static fn (): array => [$public ? '93.184.216.34' : '10.0.0.1']));
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function parcel(array $attributes): Parcel
    {
        static $number = 0;
        $number++;

        return Parcel::query()->create(['name' => 'Parcel', 'code' => 'parcel-'.$number, 'active' => true, ...$attributes]);
    }
}
