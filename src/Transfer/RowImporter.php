<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\GalleryField;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\RepeatableField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TreeSelectField;
use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Support\ContentLanguages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * One row of the file: finds the record by the import's key, turns the cells
 * into what the form would send and saves it through RecordWriter — or, in a
 * check, only asks whether it would save.
 *
 * An empty cell leaves the field as it is. A row the mode does not allow
 * (a new record in "update only", a known one in "create only") is skipped;
 * a row that cannot be saved fails, with the reasons. Neither stops the rest.
 *
 * A check counts the way the import will: a key met again further down the
 * file updates the record its first row creates.
 */
final class RowImporter
{
    private readonly bool $check;

    private readonly Target $keyTarget;

    private readonly Images $images;

    /** @var array<string,array<int,array{value:mixed,label:mixed}>> field => options */
    private array $options = [];

    /** @var array<string,Trees> */
    private array $trees = [];

    /**
     * @param  class-string<Model&Administrable>  $model
     */
    public function __construct(
        private readonly DataImport $import,
        private readonly string $model,
        private readonly Targets $targets,
        private readonly RecordWriter $writer,
        Hosts $hosts,
    ) {
        $this->check = $import->status === DataImport::CHECKING;
        $this->keyTarget = $targets->keyTarget((string) $import->option('key')) ?? throw ImportFailed::because('key', ['key' => (string) $import->option('key')]);
        $this->images = new Images($hosts, $this->check);
    }

    /**
     * @param  array<int,string>  $cells
     */
    public function import(int $number, array $cells): void
    {
        try {
            $this->row($number, $cells);
        } catch (RowProblem $problem) {
            $this->import->note($number, false, [$problem->getMessage()]);
        } catch (ValidationException $invalid) {
            $this->import->note($number, false, Arr::flatten($invalid->errors()));
        } catch (QueryException $failure) {
            report($failure);
            $this->import->note($number, false, [__('atrium::import.problems.database')]);
        }

        $this->import->processed++;
    }

    /**
     * @param  array<int,string>  $cells
     */
    private function row(int $number, array $cells): void
    {
        $values = [];

        foreach ($this->import->mapping as $index => $key) {
            $cell = $cells[$index] ?? '';

            // A target that is gone (a field taken out of the form) leaves its column out.
            if ($key !== null && $cell !== '' && $this->targets->get($key) !== null) {
                $values[$key] = $cell;
            }
        }

        $value = $values[$this->keyTarget->key] ?? throw RowProblem::because('no_key', ['field' => $this->keyTarget->label]);
        $mode = $this->import->option('mode', 'both');

        $record = $this->find($value);
        $exists = $record !== null || ($this->check && $this->import->seen('record', $value));

        if ($exists && $mode === 'create') {
            $this->import->note($number, true, [__('atrium::import.problems.exists', ['key' => $value])]);

            return;
        }

        if (! $exists && $mode === 'update') {
            $this->import->note($number, true, [__('atrium::import.problems.missing', ['key' => $value])]);

            return;
        }

        $data = $this->data($values);

        if ($this->check) {
            $errors = $this->writer->check($data, $exists, $record);

            if ($errors !== []) {
                $this->import->note($number, false, $errors);

                return;
            }

            $exists ? $this->import->updated++ : $this->import->created++;
            $this->import->remember('record', $value);

            return;
        }

        $this->writer->write($data, $record);
        $exists ? $this->import->updated++ : $this->import->created++;
    }

    /**
     * The record the key's cell names, if there is one. A field kept per
     * language is looked for in the default language.
     */
    private function find(string $value): (Model&Administrable)|null
    {
        $key = (string) $this->import->option('key');

        if ($key === Targets::ID && preg_match('/^[1-9][0-9]*\z/', $value) !== 1) {
            throw RowProblem::because('bad_id', ['value' => $value]);
        }

        $column = in_array($key, $this->writer->translatable(), true) ? $key.'->'.app(ContentLanguages::class)->default() : $key;

        return $this->model::query()->where($column, $value)->orderBy('id')->first();
    }

    /**
     * What the form would send for these cells.
     *
     * @param  array<string,string>  $values  target => cell
     * @return array<string,mixed>
     */
    private function data(array $values): array
    {
        $data = [];

        foreach ($values as $key => $cell) {
            $target = $this->targets->get($key);

            if ($target->resolve !== null) {
                $data = [...$data, ...($target->resolve)($cell, $this->import, $this->check)];

                continue;
            }

            // The id finds the record; it is never written.
            if ($target->field === null) {
                continue;
            }

            $field = $target->field;
            $value = $this->value($field, $cell);

            if ($target->locale === null) {
                $data[$field->getName()] = $value;
            } else {
                $data[$field->getName()][$target->locale] = $value;
            }
        }

        return $data;
    }

    private function value(Field $field, string $cell): mixed
    {
        return match (true) {
            $field instanceof CheckboxField => Cells::bool($cell) ?? throw RowProblem::because('not_bool', ['field' => $field->getLabel(), 'value' => $cell]),
            $field instanceof NumberField => Cells::number($cell),
            $field instanceof DateTimeField => Cells::date($cell, true),
            $field instanceof DateField => Cells::date($cell, false),
            $field instanceof MultiSelectField => array_map(fn (string $item): mixed => $this->option($field, $item), Cells::items($cell)),
            $field instanceof SelectField, $field instanceof BelongsToField => $this->option($field, $cell),
            $field instanceof TreeSelectField => $this->tree($field)->find($cell),
            $field instanceof RepeatableField => Cells::items($cell),
            $field instanceof GalleryField => array_map($this->images->one(...), Cells::addresses($cell)),
            $field instanceof ImageField => $this->images->one($cell),
            default => $cell,
        };
    }

    private function tree(TreeSelectField $field): Trees
    {
        return $this->trees[$field->getName()] ??= new Trees($field->toArray()['options'], $field->getLabel(), $this->import, $this->check);
    }

    /**
     * The option a cell names: by its value ("12", "red") or, case and
     * punctuation aside, by its value or label ("Red", "Красный").
     */
    private function option(SelectField|BelongsToField $field, string $cell): mixed
    {
        $options = $this->options[$field->getName()] ??= $field instanceof BelongsToField ? $field->getOptions() : $field->toArray()['options'];
        $label = Cells::normalize($cell);
        $found = [];

        foreach ($options as $option) {
            if ((string) $option['value'] === $cell) {
                return $option['value'];
            }

            if (Cells::normalize((string) $option['value']) === $label || Cells::normalize((string) $option['label']) === $label) {
                $found[] = $option['value'];
            }
        }

        return match (count($found)) {
            0 => throw RowProblem::because('not_option', ['field' => $field->getLabel(), 'value' => $cell]),
            1 => $found[0],
            default => throw RowProblem::because('ambiguous_option', ['field' => $field->getLabel(), 'value' => $cell]),
        };
    }
}
