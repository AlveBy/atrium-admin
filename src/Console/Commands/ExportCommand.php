<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Transfer\Availability;
use Atrium\Admin\Transfer\Exporter;
use Atrium\Admin\Transfer\RecordWriter;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * The list of any module as a CSV or Excel file, narrowed as the list screen
 * narrows it: --search, --sort and --direction, and --filter for the filters
 * the module declares. The file goes back in through `atrium:import` as it is.
 */
final class ExportCommand extends Command
{
    protected $signature = 'atrium:export
        {module : The key of the module: "news" or "catalog_products"}
        {file? : Where to write it; the format follows the extension (.csv or .xlsx). Default: the module and the day, in CSV}
        {--format= : csv or xlsx, when the file name does not say}
        {--search= : Only records the search finds}
        {--filter=* : A filter of the module, "name=value"}
        {--sort= : The column to sort by}
        {--direction= : asc or desc}';

    protected $description = 'Export the records of a module to a CSV or Excel file';

    public function handle(ResourceRegistry $resources): int
    {
        $module = (string) $this->argument('module');
        $model = $resources->model($module);

        if ($model === null || ! Availability::export($model)) {
            $this->error(__('atrium::export.console.module', ['module' => $module, 'modules' => implode(', ', array_keys(array_filter($resources->all(), Availability::export(...))))]));

            return self::FAILURE;
        }

        $file = $this->argument('file');
        $format = $this->option('format') ?? (is_string($file) ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : Exporter::CSV);

        if (! in_array($format, Exporter::FORMATS, true)) {
            $this->error(__('atrium::export.console.format', ['formats' => implode(', ', Exporter::FORMATS)]));

            return self::FAILURE;
        }

        $file ??= $module.'-'.now()->format('Y-m-d').'.'.$format;
        $query = [...$this->filters(), ...array_filter(['search' => $this->option('search'), 'sort' => $this->option('sort'), 'direction' => $this->option('direction')], static fn (?string $value): bool => $value !== null)];

        $count = Exporter::for($model)->write(Exporter::output($format, $file), (new RecordWriter($model))->list(Request::create('/', 'GET', $query)));

        $this->line(__('atrium::export.console.done', ['count' => $count, 'file' => $file]));

        return self::SUCCESS;
    }

    /**
     * @return array<string,string>
     */
    private function filters(): array
    {
        $filters = [];

        foreach ($this->option('filter') as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $filters[$name] = $value;
        }

        return $filters;
    }
}
