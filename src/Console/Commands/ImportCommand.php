<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Transfer\Availability;
use Atrium\Admin\Transfer\Cells;
use Atrium\Admin\Transfer\ImportFailed;
use Atrium\Admin\Transfer\ImportOption;
use Atrium\Admin\Transfer\ImportRunner;
use Atrium\Admin\Transfer\Settings;
use Atrium\Admin\Transfer\SourceFile;
use Atrium\Admin\Transfer\Targets;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The import of any module from the console and the scheduler: a file or a
 * URL, mapped the way an earlier import from the panel was (--like) or by its
 * headers, the columns that need it named with --map. It runs to the end and
 * prints the report; it fails when a row did not import, so a scheduled price
 * list that went wrong is noticed.
 *
 * With --pending it moves the imports started in the panel instead: the panel
 * schedules that every minute, for sites without a queue worker.
 */
final class ImportCommand extends Command
{
    protected $signature = 'atrium:import
        {module? : The key of the module: "news" or "catalog_products"}
        {source? : A CSV or Excel (xlsx) file, or its URL}
        {--like= : Map the columns as the import with this number did, and take its options}
        {--map=* : A column and the field it fills, "Column=field"; "Column=" leaves it out}
        {--key= : Find records by this column: id, code or what the module offers}
        {--mode= : both, create (new records only) or update (existing only)}
        {--option=* : An option of the module, "name=value" (a module lists its own)}
        {--check : Only check the file and report; write nothing}
        {--pending : Move the imports started in the panel a step forward}';

    protected $description = 'Import the records of a module from a CSV or Excel file';

    public function handle(SourceFile $source, ImportRunner $runner, ResourceRegistry $resources): int
    {
        if ($this->option('pending')) {
            // The scheduler runs this every minute, also on a site that has not migrated yet.
            if (! Schema::hasTable((new DataImport)->getTable())) {
                return self::SUCCESS;
            }

            foreach (DataImport::query()->whereIn('status', [DataImport::CHECKING, DataImport::IMPORTING])->get() as $import) {
                $runner->step($import, config('atrium.import.seconds'));
            }

            return self::SUCCESS;
        }

        $module = $this->argument('module');
        $model = $module === null ? null : $resources->model($module);

        if ($model === null || ! Availability::import($model)) {
            return $this->refuse(__('atrium::import.console.module', ['module' => $module, 'modules' => implode(', ', $this->modules($resources))]));
        }

        $location = $this->argument('source');

        if ($location === null) {
            return $this->refuse(__('atrium::import.console.source'));
        }

        $like = $this->option('like') === null ? null : DataImport::query()->where('module', $module)->find($this->option('like'));

        if ($this->option('like') !== null && $like === null) {
            return $this->refuse(__('atrium::import.console.like', ['id' => $this->option('like')]));
        }

        $targets = Targets::for($model);
        $options = Settings::options($model);
        $file = Str::uuid().'.csv';
        $target = DataImport::directory().'/'.$file;
        File::ensureDirectoryExists(DataImport::directory());

        try {
            $read = $this->read($source, $location, $target);
            $mapping = $this->mapping($read['headers'], $like, $targets);
            $settings = Settings::validate([
                ...Settings::defaults($options),
                'key' => Settings::key($mapping, $targets),
                ...($like->options ?? []),
                ...array_filter(['key' => $this->option('key'), 'mode' => $this->option('mode')], static fn (?string $value): bool => $value !== null),
                ...$this->moduleOptions($options),
                'mapping' => $mapping,
            ], count($read['headers']), $targets, $options);
        } catch (ImportFailed $failed) {
            File::delete($target);

            return $this->refuse($failed->getMessage());
        } catch (ValidationException $invalid) {
            File::delete($target);

            return $this->refuse(implode(PHP_EOL, Arr::flatten($invalid->errors())));
        }

        $import = DataImport::query()->create([
            ...$read,
            ...$settings,
            'module' => $module,
            'status' => DataImport::NEW,
            'original_name' => mb_substr(rawurldecode(basename((string) strtok($location, '?#'))), 0, 255),
            'file' => $file,
        ]);

        $import->begin($this->option('check') ? DataImport::CHECKING : DataImport::IMPORTING);

        while ($runner->step($import, config('atrium.import.seconds'))) {
            // Each step saves its place; the next one goes on from there.
        }

        $import->deleteFile();

        return $this->report($import);
    }

    /**
     * The keys of the modules that take imports, for the message that names one wrongly.
     *
     * @return array<int,string>
     */
    private function modules(ResourceRegistry $resources): array
    {
        return array_keys(array_filter($resources->all(), static fn (string $model): bool => Availability::import($model)));
    }

    /**
     * What --option says, "name=value", for the options the module has.
     *
     * @param  array<int,ImportOption>  $options
     * @return array<string,string>
     */
    private function moduleOptions(array $options): array
    {
        $given = [];

        foreach ($this->option('option') as $pair) {
            [$name, $value] = [...explode('=', $pair, 2), '1'];

            if (! in_array($name, array_column($options, 'name'), true)) {
                throw ImportFailed::because('option', ['option' => $name]);
            }

            $given[$name] = $value;
        }

        return $given;
    }

    /**
     * @return array{format:string,encoding:string,delimiter:string,headers:array<int,string>,total:int}
     */
    private function read(SourceFile $source, string $location, string $target): array
    {
        if (preg_match('#^https?://#i', $location) !== 1) {
            if (! is_file($location)) {
                throw ImportFailed::because('missing', ['file' => $location]);
            }

            return $source->normalize($location, $target);
        }

        $download = tempnam(sys_get_temp_dir(), 'atrium-import');

        try {
            $response = Http::timeout(120)->sink($download)->get($location);

            if (! $response->successful()) {
                throw ImportFailed::because('download', ['url' => $location]);
            }

            return $source->normalize($download, $target);
        } catch (ConnectionException) {
            throw ImportFailed::because('download', ['url' => $location]);
        } finally {
            File::delete($download);
        }
    }

    /**
     * The earlier import's mapping, column by column of the same name, or the
     * guess from the headers; then what --map says.
     *
     * @param  array<int,string>  $headers
     * @return array<int,string|null>
     */
    private function mapping(array $headers, ?DataImport $like, Targets $targets): array
    {
        $names = array_map(Cells::normalize(...), $headers);
        $mapping = $targets->guess($headers);

        if ($like !== null) {
            $known = array_combine(array_map(Cells::normalize(...), $like->headers), $like->mapping);
            $mapping = array_map(static fn (string $name): ?string => $known[$name] ?? null, $names);
        }

        foreach ($this->option('map') as $pair) {
            $equals = strrpos($pair, '=');
            $column = $equals === false ? false : array_search(Cells::normalize(substr($pair, 0, $equals)), $names, true);

            if ($column === false) {
                throw ImportFailed::because('column', ['column' => $pair]);
            }

            $field = substr($pair, $equals + 1);
            $mapping[$column] = $field === '' ? null : $field;
        }

        return $mapping;
    }

    private function report(DataImport $import): int
    {
        $this->line(__('atrium::import.console.report', $import->only(['processed', 'created', 'updated', 'skipped', 'failed', 'nodes'])));

        foreach ($import->errors ?? [] as $error) {
            $this->line(__('atrium::import.console.row', ['row' => $error['row'], 'messages' => implode(' ', $error['messages'])]));
        }

        if ($import->status === DataImport::FAILED) {
            return $this->refuse($import->message);
        }

        return $import->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
