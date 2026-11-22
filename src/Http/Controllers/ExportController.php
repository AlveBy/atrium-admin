<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Transfer\Exporter;
use Atrium\Admin\Transfer\RecordWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The list of a module as a file: what the list screen shows for the same
 * query string (search, sort, the module's filters), every page of it.
 */
final class ExportController extends Controller
{
    /** What each format is called on the wire; a temporary file has no extension to guess it from. */
    private const TYPES = [
        Exporter::CSV => 'text/csv; charset=UTF-8',
        Exporter::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(private readonly ResourceRegistry $resources) {}

    public function download(Request $request, string $module): BinaryFileResponse
    {
        $model = $this->resources->model($module);
        abort_if($model === null, 404);

        $format = $request->query('format', Exporter::CSV);
        abort_unless(in_array($format, Exporter::FORMATS, true), 422);

        $path = tempnam(sys_get_temp_dir(), 'atrium-export-');
        $exporter = Exporter::for($model);
        $exporter->write(Exporter::output($format, $path), (new RecordWriter($model))->list($request));

        return response()->download($path, $this->name($module).'.'.$format, ['Content-Type' => self::TYPES[$format]])->deleteFileAfterSend();
    }

    /**
     * "news-2026-11-22.csv": the module and the day.
     */
    private function name(string $module): string
    {
        return Str::slug($module).'-'.now()->format('Y-m-d');
    }
}
