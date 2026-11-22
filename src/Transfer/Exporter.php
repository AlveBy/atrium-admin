<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\TreeSelectField;
use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Transfer\Output\CsvOutput;
use Atrium\Admin\Transfer\Output\Output;
use Atrium\Admin\Transfer\Output\XlsxOutput;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A module's records as a file: a header row, then a row for each record the
 * list shows — the id first, then every field the import can fill, in the
 * order of the form, a field kept per language once per language. What it
 * writes is what the import reads: the same file goes back in without a
 * change, and finds each record by its id.
 */
final class Exporter
{
    public const CSV = 'csv';

    public const XLSX = 'xlsx';

    public const FORMATS = [self::CSV, self::XLSX];

    /** @var array<string,Trees> */
    private array $trees = [];

    public function __construct(private readonly Targets $targets, private readonly RecordWriter $writer, private readonly int $chunk) {}

    /**
     * @param  class-string<Model&Administrable>  $model
     */
    public static function for(string $model): self
    {
        return new self(Targets::for($model), new RecordWriter($model), (int) config('atrium.export.chunk'));
    }

    public static function output(string $format, string $path): Output
    {
        return $format === self::XLSX ? new XlsxOutput($path) : new CsvOutput($path);
    }

    /**
     * @return array<int,string>
     */
    public function header(): array
    {
        return array_values(array_map(static fn (Target $target): string => $target->label, $this->targets->all()));
    }

    /**
     * Writes the header and the records of the query; how many records.
     *
     * @param  Builder<Model&Administrable>  $query
     */
    public function write(Output $output, Builder $query): int
    {
        $output->row($this->header());
        $count = 0;

        // Ordered by id after the list's own order, so a page of records never repeats one.
        foreach ($query->orderBy($query->getModel()->getQualifiedKeyName())->lazy($this->chunk) as $record) {
            $output->row($this->row($record));
            $count++;
        }

        $output->close();

        return $count;
    }

    /**
     * @return array<int,string>
     */
    private function row(Model $record): array
    {
        $present = $this->writer->present($record);
        $cells = [];

        foreach ($this->targets->all() as $target) {
            $cells[] = match (true) {
                $target->export !== null => ($target->export)($record),
                $target->field === null => (string) $record->getKey(),
                default => $this->cell($target, $record, $present),
            };
        }

        return $cells;
    }

    /**
     * @param  array<string,mixed>  $present
     */
    private function cell(Target $target, Model $record, array $present): string
    {
        $field = $target->field;
        $name = $field->getName();

        // A date is read from the record, in the application's time zone: the form's copy is in UTC.
        if ($field instanceof DateTimeField || $field instanceof DateField) {
            $value = $record->getAttribute($name);

            return $value instanceof DateTimeInterface ? $value->format($field instanceof DateTimeField ? 'Y-m-d H:i:s' : 'Y-m-d') : (string) $value;
        }

        $value = $present[$name] ?? null;

        // A field kept per language: its language's text, or the default language's when the site has one.
        if ($target->locale !== null || in_array($name, $this->writer->translatable(), true)) {
            $value = is_array($value) ? ($value[$target->locale ?? app(ContentLanguages::class)->default()] ?? null) : null;
        }

        return match (true) {
            $value === null => '',
            $field instanceof CheckboxField => Cells::bool((string) $value) === true ? '1' : '0',
            $field instanceof TreeSelectField => $this->tree($field)->path((int) $value),
            is_array($value) => implode('|', $value),
            default => (string) $value,
        };
    }

    private function tree(TreeSelectField $field): Trees
    {
        return $this->trees[$field->getName()] ??= new Trees($field->toArray()['options'], $field->getLabel(), new DataImport);
    }
}
