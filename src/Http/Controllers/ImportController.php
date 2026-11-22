<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Transfer\ImportFailed;
use Atrium\Admin\Transfer\ImportRunner;
use Atrium\Admin\Transfer\ProcessImport;
use Atrium\Admin\Transfer\Rows;
use Atrium\Admin\Transfer\Settings;
use Atrium\Admin\Transfer\SourceFile;
use Atrium\Admin\Transfer\Targets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The import screens of any module: upload a file, map its columns, check,
 * import, read the report. The routes are `admin.{module}.import.*`; the
 * module is the route's `module` default, which Laravel passes after the
 * route's own `{import}` parameter. The progress page asks for the
 * state every second or two; with no queue worker it moves the import a step
 * forward each time it asks.
 */
final class ImportController extends Controller
{
    private const PREVIEW = 5;

    public function __construct(private readonly ResourceRegistry $resources) {}

    public function index(string $module): Response
    {
        $this->model($module);

        return Inertia::render('Admin/Transfer/Import/Index', [
            ...$this->screen($module),
            'imports' => DataImport::query()->with('user:id,name')->where('module', $module)->latest('id')->limit(20)->get()
                ->map(fn (DataImport $import): array => $this->state($import)),
            'maxSize' => config('atrium.import.max_size'),
        ]);
    }

    public function store(Request $request, string $module, SourceFile $source): RedirectResponse
    {
        $model = $this->model($module);

        $request->validate(
            ['file' => ['required', 'file', 'max:'.config('atrium.import.max_size')]],
            [],
            ['file' => __('atrium::import.upload.file')],
        );

        File::ensureDirectoryExists(DataImport::directory());
        $file = Str::uuid().'.csv';
        $target = DataImport::directory().'/'.$file;

        try {
            $read = $source->normalize($request->file('file')->getRealPath(), $target);
        } catch (ImportFailed $failed) {
            File::delete($target);

            throw ValidationException::withMessages(['file' => $failed->getMessage()]);
        }

        // The mapping of the last import of a file with these very columns.
        $previous = DataImport::query()->where('module', $module)->whereNotNull('mapping')->latest('id')->limit(20)->get()
            ->first(static fn (DataImport $import): bool => $import->headers === $read['headers']);

        $import = DataImport::query()->create([
            ...$read,
            'module' => $module,
            'admin_user_id' => $request->user(config('atrium.auth.guard', 'admin'))->id,
            'status' => DataImport::NEW,
            'original_name' => mb_substr($request->file('file')->getClientOriginalName(), 0, 255),
            'file' => $file,
            'mapping' => $previous->mapping ?? Targets::for($model)->guess($read['headers']),
            'options' => $previous?->options,
        ]);

        return redirect()->route("admin.{$module}.import.show", $import);
    }

    public function show(DataImport $import, string $module): Response
    {
        $model = $this->model($module);
        $this->belongs($import, $module);
        $targets = Targets::for($model);

        return Inertia::render('Admin/Transfer/Import/Show', [
            ...$this->screen($module),
            'import' => $this->state($import),
            'preview' => is_file($import->path()) ? Rows::preview($import->path(), self::PREVIEW) : [],
            'targets' => $targets->options(),
            'keys' => $targets->keys(),
            'modes' => DataImport::MODES,
            'moduleOptions' => array_map(static fn ($option): array => $option->toArray(), Settings::options($model)),
        ]);
    }

    /**
     * Save the mapping and the options and start the check.
     */
    public function check(Request $request, DataImport $import, string $module): RedirectResponse
    {
        $model = $this->model($module);
        $this->belongs($import, $module);
        abort_unless($import->isOpen(), 409);

        $settings = Settings::validate($request->all(), count($import->headers), Targets::for($model), Settings::options($model));
        $import->forceFill($settings)->save();
        $import->begin(DataImport::CHECKING);
        ProcessImport::start($import);

        return redirect()->route("admin.{$module}.import.show", $import);
    }

    /**
     * Import what the check has seen.
     */
    public function run(DataImport $import, string $module): RedirectResponse
    {
        $this->model($module);
        $this->belongs($import, $module);
        abort_unless($import->status === DataImport::CHECKED, 409);

        $import->begin(DataImport::IMPORTING);
        ProcessImport::start($import);

        return redirect()->route("admin.{$module}.import.show", $import);
    }

    /**
     * Where the import stands; without a queue worker, a step forward first.
     */
    public function step(DataImport $import, string $module, ImportRunner $runner): JsonResponse
    {
        $this->model($module);
        $this->belongs($import, $module);

        if (! ProcessImport::queued()) {
            $runner->step($import, config('atrium.import.request_seconds'));
        }

        return response()->json($this->state($import->refresh()));
    }

    public function cancel(DataImport $import, string $module): RedirectResponse
    {
        $this->model($module);
        $this->belongs($import, $module);

        // Wait for a step that is under way: it would otherwise finish the import after this.
        Cache::lock('atrium-import:'.$import->id, 10)->block(config('atrium.import.seconds') + 5, static function () use ($import): void {
            $import->refresh();

            if ($import->status !== DataImport::DONE) {
                $import->stop(DataImport::CANCELLED);
                $import->deleteFile();
            }
        });

        return redirect()->route("admin.{$module}.import.show", $import);
    }

    /**
     * @return class-string<Model&Administrable>
     */
    private function model(string $module): string
    {
        $model = $this->resources->model($module);

        abort_if($model === null, 404);

        return $model;
    }

    /**
     * An import is opened under its own module only: the permission checked
     * for the route is the module's, so another module's import is not found.
     */
    private function belongs(DataImport $import, string $module): void
    {
        abort_unless($import->module === $module, 404);
    }

    /**
     * What every screen of the import needs to build its links.
     *
     * @return array<string,mixed>
     */
    private function screen(string $module): array
    {
        return [
            'module' => $module,
            'routeName' => "admin.{$module}",
            'title' => ($this->model($module))::getAdminLabel(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function state(DataImport $import): array
    {
        return [
            ...$import->only([
                'id', 'status', 'original_name', 'format', 'encoding', 'delimiter', 'headers', 'mapping', 'total',
                'processed', 'created', 'updated', 'skipped', 'failed', 'nodes', 'errors', 'message',
            ]),
            'options' => [...Settings::defaults(Settings::options($this->model($import->module))), ...($import->options ?? [])],
            'user' => $import->user?->name,
            'created_at' => $import->created_at->toIso8601String(),
            'started_at' => $import->started_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
        ];
    }
}
