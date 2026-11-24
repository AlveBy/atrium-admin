<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\History\Trash;
use Atrium\Admin\Hooks\Refused;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\TrashedRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The trash on screen: what was deleted in the modules the administrator may
 * view, of the site they work with and of the modules all sites share,
 * newest first. A record is restored with "{module}.restore" and deleted for
 * good with "{module}.force_delete".
 */
final class TrashController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly ApiResources $resources, private readonly Trash $trash) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(['module' => ['nullable', 'string', 'max:100']]);
        $modules = $this->visibleModules();

        $entries = $this->trash->entries()
            ->whereIn('module', array_keys($modules))
            ->when($filters['module'] ?? null, static fn (Builder $query, string $module) => $query->where('module', $module))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Trash/Index', [
            'entries' => $entries->through(fn (TrashedRecord $entry): array => [
                'id' => $entry->id,
                'at' => $entry->created_at->toIso8601String(),
                'admin' => $entry->admin_name,
                'module' => $modules[$entry->module],
                'record' => $entry->record_title,
                'restoreUrl' => $this->may($entry->module, 'restore') ? route('admin.trash.restore', $entry->id) : null,
                'deleteUrl' => $this->may($entry->module, 'force_delete') ? route('admin.trash.destroy', $entry->id) : null,
            ]),
            'filters' => ['module' => $filters['module'] ?? ''],
            'modules' => array_map(static fn (string $key, string $label): array => ['key' => $key, 'label' => $label], array_keys($modules), $modules),
            'days' => config()->integer('atrium.trash.days'),
        ]);
    }

    /**
     * Brings the record back and opens it.
     */
    public function restore(int $entry): RedirectResponse
    {
        $trashed = $this->find($entry, 'restore');
        $record = $this->trash->record($trashed);

        abort_if($record === null, 404);

        $this->trash->restore($trashed, $record);

        return redirect()->route("admin.{$trashed->module}.edit", $trashed->record_id)->with('success', __('atrium::trash.restored'));
    }

    /**
     * Deletes the record for good.
     */
    public function destroy(int $entry): RedirectResponse
    {
        try {
            if (! $this->trash->purge($this->find($entry, 'force_delete'))) {
                return back()->with('error', __('atrium::resource.cannot_delete'));
            }
        } catch (Refused $refused) {
            return back()->with('error', $refused->getMessage());
        }

        return redirect()->route('admin.trash.index')->with('success', __('atrium::trash.deleted'));
    }

    /**
     * An entry of this site that the administrator may act on ($action of
     * its module).
     */
    private function find(int $id, string $action): TrashedRecord
    {
        $entry = $this->trash->entries()->whereIn('module', array_keys($this->visibleModules()))->findOrFail($id);

        abort_unless($this->may($entry->module, $action), 403);

        return $entry;
    }

    /**
     * The modules with the trash that this administrator may view.
     *
     * @return array<string,string> key => label
     */
    private function visibleModules(): array
    {
        $modules = [];

        foreach ($this->resources->modules() as $key => $model) {
            if ($this->trash->enabledFor($model) && $this->may($key, 'view')) {
                $modules[$key] = $this->resources->label($key);
            }
        }

        natcasesort($modules);

        return $modules;
    }

    private function may(string $module, string $action): bool
    {
        $user = auth()->guard((string) config('atrium.auth.guard', 'admin'))->user();

        return ! $user instanceof AdminUser || $user->hasAdminPermission("{$module}.{$action}");
    }
}
