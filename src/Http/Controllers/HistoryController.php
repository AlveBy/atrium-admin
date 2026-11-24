<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\History\ActivityLog;
use Atrium\Admin\Models\Activity;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\Sites;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The activity log on screen: all of it, narrowed by module, administrator
 * and dates, and the history of one record, from its form's "History"
 * button. An administrator sees the entries of the modules their roles open,
 * of the site they work with and of the modules all sites share.
 */
final class HistoryController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly ApiResources $resources, private readonly Sites $sites) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:100'],
            'admin' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $modules = $this->visibleModules();

        $entries = $this->entries()
            ->whereIn('module', array_keys($modules))
            ->when($filters['module'] ?? null, static fn (Builder $query, string $module) => $query->where('module', $module))
            ->when($filters['admin'] ?? null, static fn (Builder $query, int|string $admin) => $query->where('admin_user_id', $admin))
            ->when($filters['from'] ?? null, static fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)))
            ->when($filters['to'] ?? null, static fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/History/Index', [
            'entries' => $entries->setCollection($this->present($entries->getCollection(), $modules)),
            'filters' => [
                'module' => $filters['module'] ?? '',
                'admin' => $filters['admin'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'modules' => array_map(static fn (string $key, string $label): array => ['key' => $key, 'label' => $label], array_keys($modules), $modules),
            'admins' => AdminUser::query()->orderBy('name')->get(['id', 'name'])->map(static fn (AdminUser $admin): array => ['id' => $admin->id, 'name' => $admin->name])->all(),
        ]);
    }

    /**
     * The history of one record of the module. Its route checks "view" of
     * the module; the record must be one this site has.
     */
    public function record(int $id, string $module): Response
    {
        $model = $this->resources->modules()[$module];
        $record = $model::query()->findOrFail($id);
        $modules = [$module => $this->resources->label($module)];

        $entries = $this->entries()
            ->where('module', $module)
            ->where('record_id', $record->getKey())
            ->paginate(self::PER_PAGE);

        return Inertia::render('Admin/History/Record', [
            'entries' => $entries->setCollection($this->present($entries->getCollection(), $modules)),
            'title' => __('atrium::history.record_title', ['title' => ActivityLog::titleOf($record)]),
            'moduleLabel' => $modules[$module],
            'indexUrl' => route("admin.{$module}.index"),
            'editUrl' => route("admin.{$module}.edit", $id),
        ]);
    }

    /**
     * The entries of the site the panel works with and of the shared
     * modules, newest first.
     *
     * @return Builder<Activity>
     */
    private function entries(): Builder
    {
        $site = $this->sites->current()->id;

        return Activity::query()
            ->where(static fn (Builder $query) => $query->whereNull('site_id')->orWhere('site_id', $site))
            ->orderByDesc('id');
    }

    /**
     * The modules whose history this administrator may read: those that keep
     * one and that they may view.
     *
     * @return array<string,string> key => label
     */
    private function visibleModules(): array
    {
        $user = auth()->guard((string) config('atrium.auth.guard', 'admin'))->user();
        $history = app(ActivityLog::class);
        $modules = [];

        foreach ($this->resources->modules() as $key => $model) {
            if ($history->enabledFor($model) && (! $user instanceof AdminUser || $user->hasAdminPermission("{$key}.view"))) {
                $modules[$key] = $this->resources->label($key);
            }
        }

        natcasesort($modules);

        return $modules;
    }

    /**
     * Entries as the screen shows them: the module's name, the field's
     * label, and a link to the record while it is still there.
     *
     * @param  Collection<int,Activity>  $entries
     * @param  array<string,string>  $modules
     * @return BaseCollection<int,array{id:int,at:string,event:string,source:string,admin:string|null,module:string,record:string|null,url:string|null,changes:array<int,array{field:string,label:string,old:mixed,new:mixed,secret:bool}>}>
     */
    private function present(Collection $entries, array $modules): BaseCollection
    {
        $existing = $this->existing($entries);
        $labels = [];

        foreach ($entries->pluck('module')->unique() as $module) {
            $labels[$module] = $this->fieldLabels($module);
        }

        return $entries->toBase()->map(fn (Activity $entry): array => [
            'id' => $entry->id,
            'at' => $entry->created_at->toIso8601String(),
            'event' => $entry->event,
            'source' => $entry->source,
            'admin' => $entry->admin_name,
            'module' => $modules[$entry->module],
            'record' => $entry->record_title,
            'url' => in_array($entry->record_id, $existing[$entry->module]) ? route("admin.{$entry->module}.edit", $entry->record_id) : null,
            'changes' => $this->changes($entry, $labels[$entry->module]),
        ]);
    }

    /**
     * Ids of the listed records that are still there, by module: one query a
     * module.
     *
     * @param  Collection<int,Activity>  $entries
     * @return array<string,array<int,mixed>>
     */
    private function existing(Collection $entries): array
    {
        $existing = [];

        foreach ($entries->groupBy('module') as $module => $group) {
            $model = $this->resources->modules()[$module];
            $existing[$module] = $model::query()->whereKey($group->pluck('record_id')->all())->pluck((new $model)->getKeyName())->all();
        }

        return $existing;
    }

    /**
     * @param  array<string,string>  $labels  field => label
     * @return array<int,array{field:string,label:string,old:mixed,new:mixed,secret:bool}>
     */
    private function changes(Activity $entry, array $labels): array
    {
        $changes = [];

        foreach ($entry->changes ?? [] as $field => $change) {
            $changes[] = [
                'field' => $field,
                'label' => $labels[$field] ?? Str::headline($field),
                'old' => $change['old'] ?? null,
                'new' => $change['new'] ?? null,
                'secret' => $change['secret'] ?? false,
            ];
        }

        return $changes;
    }

    /**
     * @return array<string,string> field => label on the module's form
     */
    private function fieldLabels(string $module): array
    {
        $labels = [];

        foreach (AdminController::fieldsOf($this->resources->modules()[$module]) as $field) {
            $labels[$field->getName()] = $field->getLabel();
        }

        return $labels;
    }

    /**
     * Where the "History" button of a record leads, or null when the module
     * keeps no history.
     */
    public static function urlFor(string $routeName, int|string $id): ?string
    {
        return Route::has($routeName.'.history') ? route($routeName.'.history', $id) : null;
    }
}
