<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Tests\Fixtures\Chapter;
use Atrium\Admin\Tests\Fixtures\Task;
use Atrium\Admin\Tests\Fixtures\Ticket;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Views\Board;
use Atrium\Admin\Views\KanbanView;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What a board is made of: its columns, the counts and pages of the cards in
 * them, the order value a dropped card gets, and the text under a title.
 */
final class KanbanBoardTest extends TestCase
{
    /**
     * @return array<string,Field>
     */
    private function fields(string $model): array
    {
        $fields = [];

        foreach ($model::getAdministrationFields() as $field) {
            $fields[$field->getName()] = $field;
        }

        return $fields;
    }

    private function board(?string $order = 'rank', int $per = 2): Board
    {
        $view = KanbanView::make('status')->perColumn($per);

        if ($order !== null) {
            $view->order($order);
        }

        $fields = $this->fields(Task::class);

        return new Board($view, $fields['status'], ['lane_id' => $fields['lane_id'], 'due_on' => $fields['due_on'], 'urgent' => $fields['urgent']]);
    }

    /**
     * @param  array<int,int>  $ranks
     * @return array<int,int> ids, in the order of the ranks
     */
    private function column(string $status, array $ranks): array
    {
        $ids = [];

        foreach ($ranks as $rank) {
            $ids[] = Task::query()->create(['title' => "{$status} {$rank}", 'status' => $status, 'rank' => $rank])->id;
        }

        return $ids;
    }

    public function test_the_columns_are_the_options_of_the_field(): void
    {
        $board = $this->board();

        $this->assertSame([['value' => 'todo', 'label' => 'To do'], ['value' => 'doing', 'label' => 'Doing'], ['value' => 'done', 'label' => 'Done']], $board->columns());
        $this->assertSame($board->columns(), $board->columns());
        $this->assertTrue($board->hasColumn('doing'));
        $this->assertFalse($board->hasColumn('later'));
        $this->assertFalse($board->hasColumn(''));
        $this->assertSame('kanban', $board->view()->type());
    }

    public function test_the_columns_of_a_relation_are_its_records_and_a_field_of_another_kind_has_none(): void
    {
        $first = Chapter::query()->create(['name' => 'Lane one']);
        $second = Chapter::query()->create(['name' => 'Lane two']);
        $fields = $this->fields(Ticket::class);

        $board = new Board(KanbanView::make('lane_id'), $fields['lane_id']);

        $this->assertSame([['value' => (string) $first->id, 'label' => 'Lane one'], ['value' => (string) $second->id, 'label' => 'Lane two']], $board->columns());
        $this->assertSame([], (new Board(KanbanView::make('name'), $fields['name']))->columns());
    }

    public function test_a_relation_makes_no_more_than_forty_columns(): void
    {
        foreach (range(1, Board::MAX_COLUMNS + 3) as $number) {
            Chapter::query()->create(['name' => "Lane {$number}"]);
        }

        $board = new Board(KanbanView::make('lane_id'), $this->fields(Ticket::class)['lane_id']);

        $this->assertCount(Board::MAX_COLUMNS, $board->columns());
        $this->assertSame('Lane 40', $board->columns()[39]['label']);
    }

    public function test_the_cards_are_counted_per_column_and_a_stranger_goes_to_no_value(): void
    {
        $this->column('todo', [1, 2, 3]);
        $this->column('done', [1]);
        $this->column('archived', [1, 2]);
        Task::query()->create(['title' => 'Nothing', 'status' => null, 'rank' => 1]);

        $counts = $this->board()->counts(Task::query());
        ksort($counts);

        $this->assertSame(['' => 3, 'done' => 1, 'todo' => 3], $counts);
        $this->assertSame(['' => 0], $this->board()->counts(Task::query()->whereKey(0)));
    }

    public function test_a_column_is_its_value_and_no_value_is_everything_else(): void
    {
        $todo = $this->column('todo', [1, 2]);
        $this->column('done', [1]);
        $old = $this->column('archived', [1]);
        $none = Task::query()->create(['title' => 'Nothing', 'status' => null])->id;

        $board = $this->board();

        $this->assertSame($todo, $board->inColumn(Task::query(), 'todo')->orderBy('id')->pluck('id')->all());
        $this->assertSame([$old[0], $none], $board->inColumn(Task::query(), null)->orderBy('id')->pluck('id')->all());
        $this->assertSame([], $board->inColumn(Task::query(), 'doing')->pluck('id')->all());
    }

    public function test_a_page_of_a_column_is_in_the_order_of_the_cards_and_tells_there_is_more(): void
    {
        [$a, $b, $c] = $this->column('todo', [30, 10, 20]);
        $this->column('done', [5]);

        $first = $this->board()->page(Task::query()->orderByDesc('id'), 'todo', 1);
        $this->assertSame([$b, $c], array_column($first->items(), 'id'));
        $this->assertTrue($first->hasMorePages());

        $second = $this->board()->page(Task::query(), 'todo', 2);
        $this->assertSame([$a], array_column($second->items(), 'id'));
        $this->assertFalse($second->hasMorePages());

        $this->assertSame([$b, $c], array_column($this->board()->page(Task::query(), 'todo', 0)->items(), 'id'), 'No page before the first.');
        $this->assertSame([$b, $c], array_column($this->board()->page(Task::query(), 'todo', -3)->items(), 'id'));
    }

    public function test_equal_orders_fall_back_to_the_id(): void
    {
        [$a, $b, $c] = $this->column('todo', [7, 7, 7]);

        $this->assertSame([$a, $b, $c], array_column($this->board(per: 5)->page(Task::query()->orderByDesc('id'), 'todo', 1)->items(), 'id'));
    }

    public function test_without_an_order_a_column_is_in_the_order_of_the_list(): void
    {
        [$a, $b, $c] = $this->column('todo', [30, 10, 20]);

        $this->assertSame([$c, $b, $a], array_column($this->board(null, 5)->page(Task::query()->orderByDesc('id'), 'todo', 1)->items(), 'id'));
    }

    public function test_a_card_dropped_at_the_end_goes_a_step_below_the_last(): void
    {
        [, $b] = $this->column('todo', [10, 20]);
        $siblings = Task::query()->where('status', 'todo');

        $this->assertSame(['order' => 20 + Board::STEP, 'shift' => []], $this->board()->place($siblings, $b));
        $this->assertSame(['order' => 20 + Board::STEP, 'shift' => []], $this->board()->place($siblings, 999999), 'After a card that is not there: at the end.');
    }

    public function test_a_card_dropped_into_an_empty_column_gets_the_first_step(): void
    {
        $this->assertSame(['order' => Board::STEP, 'shift' => []], $this->board()->place(Task::query()->where('status', 'doing'), null));
        $this->assertSame(['order' => Board::STEP, 'shift' => []], $this->board()->place(Task::query()->where('status', 'doing'), 5));
    }

    public function test_a_card_dropped_between_two_takes_the_middle_and_nobody_moves(): void
    {
        [$a] = $this->column('todo', [10, 30]);

        $this->assertSame(['order' => 20, 'shift' => []], $this->board()->place(Task::query()->where('status', 'todo'), $a));
    }

    public function test_a_card_dropped_at_the_top_goes_a_step_above_the_first_if_there_is_room(): void
    {
        $this->column('todo', [25, 40]);

        $this->assertSame(['order' => 15, 'shift' => []], $this->board()->place(Task::query()->where('status', 'todo'), null));
    }

    public function test_the_order_never_goes_below_zero_so_the_column_is_numbered_again(): void
    {
        [$a, $b] = $this->column('todo', [5, 40]);

        $this->assertSame(['order' => 10, 'shift' => [$a => 20, $b => 30]], $this->board()->place(Task::query()->where('status', 'todo'), null));
    }

    public function test_a_card_that_has_no_room_between_its_neighbours_renumbers_the_column(): void
    {
        [$a, $b, $c] = $this->column('todo', [10, 11, 12]);

        $placed = $this->board()->place(Task::query()->where('status', 'todo'), $a);

        $this->assertSame(['order' => 20, 'shift' => [$b => 30, $c => 40]], $placed);
    }

    public function test_a_renumbering_leaves_alone_the_cards_that_already_have_their_value(): void
    {
        [$a, $b] = $this->column('todo', [10, 11, 40]);

        $this->assertSame(['order' => 20, 'shift' => [$b => 30]], $this->board()->place(Task::query()->where('status', 'todo'), $a));
    }

    public function test_a_card_after_the_last_has_room_even_when_the_others_are_tight(): void
    {
        [, , $c] = $this->column('todo', [1, 2, 3]);

        $this->assertSame(['order' => 13, 'shift' => []], $this->board()->place(Task::query()->where('status', 'todo'), $c));
    }

    public function test_two_cards_one_apart_have_no_room_but_two_apart_have(): void
    {
        [$a] = $this->column('todo', [10, 11]);
        $this->assertSame(['order' => 20, 'shift' => [$a + 1 => 30]], $this->board()->place(Task::query()->where('status', 'todo'), $a));

        Task::query()->delete();
        [$a] = $this->column('todo', [10, 12]);
        $this->assertSame(['order' => 11, 'shift' => []], $this->board()->place(Task::query()->where('status', 'todo'), $a));
    }

    public function test_a_card_shows_its_fields_as_text(): void
    {
        $lane = Chapter::query()->create(['name' => 'Lane one']);
        $one = Task::query()->create(['title' => 'One', 'status' => 'todo', 'lane_id' => $lane->id, 'due_on' => '2026-12-31', 'urgent' => true]);
        $two = Task::query()->create(['title' => 'Two', 'status' => 'todo']);
        Carbon::setLocale('en');

        $texts = $this->board()->texts(Task::query()->orderBy('id')->get());

        $this->assertSame([
            ['label' => 'Lane', 'text' => 'Lane one'],
            ['label' => 'Due', 'text' => '31 Dec 2026'],
            ['label' => 'Urgent', 'text' => '✓'],
        ], $texts[$one->id]);
        $this->assertSame([
            ['label' => 'Lane', 'text' => ''],
            ['label' => 'Due', 'text' => ''],
            ['label' => 'Urgent', 'text' => '—'],
        ], $texts[$two->id]);
    }

    public function test_a_lane_that_is_gone_is_an_empty_text(): void
    {
        $task = Task::query()->create(['title' => 'One', 'status' => 'todo', 'lane_id' => 999]);

        $this->assertSame('', $this->board()->texts(Task::query()->get())[$task->id][0]['text']);
    }

    public function test_the_text_of_other_fields_is_short_plain_and_labelled_by_the_options(): void
    {
        $record = new Task(['title' => '<b>Bold</b> '.str_repeat('Long ', 40), 'status' => 'doing', 'rank' => 7]);
        $record->id = 1;
        $fields = $this->fields(Task::class);

        $board = new Board(KanbanView::make('status'), $fields['status'], ['title' => $fields['title'], 'status' => $fields['status'], 'rank' => $fields['rank']]);
        $texts = $board->texts(new Collection([$record]))[1];

        $this->assertStringStartsWith('Bold Long', $texts[0]['text']);
        $this->assertLessThanOrEqual(83, mb_strlen($texts[0]['text']));
        $this->assertGreaterThan(70, mb_strlen($texts[0]['text']));
        $this->assertStringEndsWith('...', $texts[0]['text']);
        $this->assertSame('Doing', $texts[1]['text']);
        $this->assertSame('7', $texts[2]['text']);
    }

    public function test_a_select_value_the_options_lost_shows_as_it_is(): void
    {
        $record = new Task(['title' => 'A', 'status' => 'archived']);
        $record->id = 1;
        $fields = $this->fields(Task::class);

        $board = new Board(KanbanView::make('status'), $fields['status'], ['status' => $fields['status']]);

        $this->assertSame('archived', $board->texts(new Collection([$record]))[1][0]['text']);
    }

    public function test_a_date_with_a_time_shows_the_time(): void
    {
        $record = new Task(['title' => 'A']);
        $record->id = 1;
        $record->setAttribute('moment', Carbon::parse('2026-12-31 14:05:00'));
        $field = DateTimeField::make('moment')->label('Moment');

        $board = new Board(KanbanView::make('status'), $this->fields(Task::class)['status'], ['moment' => $field]);

        $this->assertSame('31 Dec 2026, 14:05', $board->texts(new Collection([$record]))[1][0]['text']);
    }

    public function test_a_value_of_no_known_kind_has_no_text(): void
    {
        $record = new Task(['title' => 'A']);
        $record->id = 1;
        $record->setAttribute('tags', ['a', 'b']);
        $field = TextField::make('tags')->label('Tags');

        $board = new Board(KanbanView::make('status'), $this->fields(Task::class)['status'], ['tags' => $field]);

        $this->assertSame('', $board->texts(new Collection([$record]))[1][0]['text']);
    }

    public function test_a_relation_is_asked_once_per_field_not_per_card(): void
    {
        $lane = Chapter::query()->create(['name' => 'Lane one']);
        Task::query()->create(['title' => 'One', 'status' => 'todo', 'lane_id' => $lane->id]);
        Task::query()->create(['title' => 'Two', 'status' => 'todo', 'lane_id' => $lane->id]);
        $records = Task::query()->get();

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->board()->texts($records);

        $this->assertCount(1, $queries);
    }

    public function test_number_and_select_fields_make_the_kinds_a_board_names(): void
    {
        $this->assertSame('select', SelectField::make('a')->getType());
        $this->assertSame('belongsTo', BelongsToField::make('b')->getType());
        $this->assertSame('number', NumberField::make('c')->getType());
    }
}
