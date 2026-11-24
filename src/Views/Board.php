<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Views;

use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\SelectField;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The columns of a KanbanView, the cards in them and the place a dropped
 * card takes: the arithmetic and the queries, with nothing about the page.
 *
 * Every query starts from the one the module's list builds (its search, its
 * filters, its site), so the board shows what the table would.
 */
final class Board
{
    /** The gap left between the `order` values of two cards, so a card fits between. */
    public const STEP = 10;

    /** Columns of a field with more records than this: the rest go to "No value". */
    public const MAX_COLUMNS = 40;

    /** @var array<int,array{value:string,label:string}>|null */
    private ?array $columns = null;

    /**
     * @param  array<string,Field>  $cardFields  by name, the fields a card shows
     */
    public function __construct(
        private readonly KanbanView $view,
        private readonly Field $field,
        private readonly array $cardFields = [],
    ) {}

    public function view(): KanbanView
    {
        return $this->view;
    }

    /**
     * The options of the field, one column each.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $options = match (true) {
            $this->field instanceof SelectField => $this->field->getOptions(),
            $this->field instanceof BelongsToField => array_slice($this->field->getOptions(), 0, self::MAX_COLUMNS),
            default => [],
        };

        return $this->columns = array_values(array_map(
            static fn (array $option): array => ['value' => (string) $option['value'], 'label' => (string) $option['label']],
            $options,
        ));
    }

    public function hasColumn(string $value): bool
    {
        return in_array($value, array_column($this->columns(), 'value'), true);
    }

    /**
     * How many records each column has, by value; the records no column
     * takes (nothing chosen, or a value the options lost) under ''.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array<string,int>
     */
    public function counts(Builder $query): array
    {
        $column = $this->field->getColumn();
        $rows = (clone $query)->reorder()->toBase()
            ->select($query->getModel()->qualifyColumn($column).' as board_value')
            ->selectRaw('count(*) as board_count')
            ->groupBy($query->getModel()->qualifyColumn($column))
            ->get();

        $counts = ['' => 0];

        foreach ($rows as $row) {
            $key = $this->hasColumn((string) $row->board_value) ? (string) $row->board_value : '';
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->board_count;
        }

        return $counts;
    }

    /**
     * The records of one column, or of the "No value" one when the column is null.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function inColumn(Builder $query, ?string $value): Builder
    {
        $column = $query->getModel()->qualifyColumn($this->field->getColumn());

        if ($value !== null) {
            return $query->where($column, $value);
        }

        return $query->where(function (Builder $where) use ($column): void {
            $where->whereNull($column)->orWhereNotIn($column, array_column($this->columns(), 'value'));
        });
    }

    /**
     * One page of a column's cards, in the order of the cards when the view
     * has one.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function page(Builder $query, ?string $value, int $page = 1): Paginator
    {
        $query = $this->inColumn($query, $value);

        if ($this->view->getOrder() !== null) {
            $query->reorder()
                ->orderBy($query->getModel()->qualifyColumn($this->view->getOrder()))
                ->orderBy($query->getModel()->getQualifiedKeyName());
        }

        return $query->simplePaginate($this->view->getPerColumn(), ['*'], 'page', max(1, $page));
    }

    /**
     * Where a card dropped in a column after the card `$after` (null: at
     * the top; a card that is not in the column: at the bottom) gets its
     * order value. The cards that must make room for it get new values too.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $siblings  the column's other cards
     * @return array{order:int,shift:array<int,int>} the card's value, and id => value of the others that change
     */
    public function place(Builder $siblings, ?int $after): array
    {
        $order = (string) $this->view->getOrder();
        $key = $siblings->getModel()->getKeyName();

        /** @var array<int,int> $values id => order value, top to bottom */
        $values = array_map(intval(...), $siblings->reorder()->toBase()
            ->orderBy($siblings->getModel()->qualifyColumn($order))
            ->orderBy($siblings->getModel()->getQualifiedKeyName())
            ->pluck($order, $key)
            ->all());

        $ids = array_keys($values);
        $found = $after === null ? false : array_search($after, $ids, true);
        $at = $after === null ? 0 : ($found === false ? count($ids) : $found + 1);

        $above = $at > 0 ? $values[$ids[$at - 1]] : null;
        $below = $at < count($ids) ? $values[$ids[$at]] : null;

        $fits = match (true) {
            $above === null && $below === null => self::STEP,
            $above === null => $below - self::STEP >= 0 ? $below - self::STEP : null,
            $below === null => $above + self::STEP,
            default => $below - $above >= 2 ? $above + intdiv($below - $above, 2) : null,
        };

        if ($fits !== null) {
            return ['order' => $fits, 'shift' => []];
        }

        // No room between the neighbours: the whole column is numbered again.
        $line = $ids;
        array_splice($line, $at, 0, [0]);
        $shift = [];

        foreach ($line as $position => $id) {
            if ($id !== 0 && $values[$id] !== ($position + 1) * self::STEP) {
                $shift[$id] = ($position + 1) * self::STEP;
            }
        }

        return ['order' => ($at + 1) * self::STEP, 'shift' => $shift];
    }

    /**
     * The fields under a card's title: label and text, by record id.
     *
     * @param  Collection<int,Model>  $records
     * @return array<int|string,array<int,array{label:string,text:string}>>
     */
    public function texts(Collection $records): array
    {
        $names = $this->belongsToLabels($records);
        $texts = [];

        foreach ($records as $record) {
            foreach ($this->cardFields as $name => $field) {
                $texts[$record->getKey()][] = ['label' => $field->getLabel(), 'text' => $this->text($field, $record, $names[$name] ?? [])];
            }
        }

        return $texts;
    }

    /**
     * @param  array<string,string>  $names  the related records' names, by value
     */
    private function text(Field $field, Model $record, array $names): string
    {
        $value = $record->getAttribute($field->getName());

        if ($value === null || $value === '') {
            return '';
        }

        if ($field instanceof BelongsToField) {
            return $names[(string) $value] ?? '';
        }

        if ($field instanceof SelectField) {
            $key = $value instanceof BackedEnum ? $value->value : $value;

            return is_scalar($key) ? (string) (array_column($field->getOptions(), 'label', 'value')[$key] ?? $key) : '';
        }

        return match (true) {
            is_bool($value) => $value ? '✓' : '—',
            $value instanceof DateTimeInterface => Carbon::instance($value)->translatedFormat($field->getType() === 'datetime' ? 'j M Y, H:i' : 'j M Y'),
            is_scalar($value) => Str::limit(trim(strip_tags((string) $value)), 80),
            default => '',
        };
    }

    /**
     * The names of the records the cards point to, one query per field.
     *
     * @param  Collection<int,Model>  $records
     * @return array<string,array<string,string>> field => value => name
     */
    private function belongsToLabels(Collection $records): array
    {
        $names = [];

        foreach ($this->cardFields as $name => $field) {
            $related = $field instanceof BelongsToField ? $field->getModelClass() : null;
            $ids = $records->pluck($name)->filter(static fn (mixed $id): bool => is_scalar($id) && $id !== '')->unique()->values()->all();

            if ($field instanceof BelongsToField && $related !== null && $ids !== []) {
                $names[$name] = $related::query()->whereIn($field->getValueColumn(), $ids)->get()
                    ->mapWithKeys(static fn (Model $model): array => [(string) $model->getAttribute($field->getValueColumn()) => (string) $model->getAttribute($field->getDisplayColumn())])
                    ->all();
            }
        }

        return $names;
    }
}
