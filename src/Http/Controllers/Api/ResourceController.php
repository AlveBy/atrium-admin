<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers\Api;

use Atrium\Admin\Actions\Action;
use Atrium\Admin\Actions\ActionResult;
use Atrium\Admin\Actions\BulkAction;
use Atrium\Admin\Actions\ModuleActions;
use Atrium\Admin\Actions\RecordAction;
use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Filters\Filter;
use Atrium\Admin\Filters\ModuleFilters;
use Atrium\Admin\History\Revisions;
use Atrium\Admin\Hooks\Refused;
use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Http\Controllers\FileManagerController;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Revision;
use Atrium\Admin\Support\Like;
use Atrium\Admin\Support\PublicUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Any admin module through the API: list, read, create, update, delete.
 *
 * Extends AdminController for one reason — to save by the same rules as the
 * form: the same field validation, checkboxes, relations, SEO, model hooks.
 * The model is chosen by the key in the URL (`/api/v1/articles`).
 *
 * Action names are its own (listItems, not index): the parent index/update
 * return Inertia pages and redirects and cannot be overridden with JSON.
 */
class ResourceController extends AdminController
{
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly ApiResources $resources) {}

    public function listItems(Request $request, string $resource = ''): JsonResponse
    {
        $this->useResource($request, $resource, 'read', Permissions::VIEW);

        $query = $this->model::query()->with($this->eagerRelations());
        $columns = $this->filterableColumns();
        $codes = $this->contentLanguages()->codes();

        $defined = app(ModuleFilters::class)->for($this->model, $request->user());

        foreach ((array) $request->query('filter', []) as $column => $value) {
            // A filter of the list (the module's or another module's) narrows
            // as it does there; a value it does not know is no filter.
            if (($defined[$column] ?? null) instanceof Filter) {
                $chosen = $defined[$column]->value($value);

                if ($chosen !== null) {
                    $defined[$column]->apply($query, $chosen);
                }

                continue;
            }

            // A field added by a package (custom fields) filters itself.
            if (Hook::apply('atrium.query.filter', false, $query, (string) $column, $value)) {
                continue;
            }

            if (! in_array($column, $columns, true)) {
                continue;
            }

            // filter[code]=hallo finds the record whatever language "hallo" is in.
            if ($this->isTranslatable($column)) {
                $query->where(function ($q) use ($column, $value, $codes) {
                    foreach ($codes as $code) {
                        $q->orWhere("{$column}->{$code}", $value);
                    }
                });
            } else {
                $query->where($column, $value);
            }
        }

        $search = $request->query('search');

        // search[]=x is not a search: it is ignored, not a reason for a 500.
        if (is_string($search) && $search !== '') {
            $searchable = collect(Hook::apply('atrium.module.columns', $this->model::getAdministrationListColumns(), $this->model))
                ->map(fn ($column) => $column->toArray())
                ->where('searchable', true)
                ->pluck('name')
                ->all();

            $query->where(function ($q) use ($searchable, $search, $codes) {
                foreach ($searchable as $column) {
                    if (Hook::apply('atrium.query.search', false, $q, $column, $search)) {
                        continue;
                    }

                    if ($this->isTranslatable($column)) {
                        $q->orWhere(fn ($w) => $w->whereTranslationLike($column, $search, $codes));
                    } else {
                        Like::contains($q, $column, $search, 'or');
                    }
                }
            });
        }

        $sort = $request->query('sort');
        $sort = is_string($sort) ? $sort : 'id';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (! Hook::apply('atrium.query.sort', false, $query, $sort, $direction)) {
            $sort = in_array($sort, $columns, true) ? $sort : 'id';
            $query->orderBy($this->isTranslatable($sort) ? $sort.'->'.$this->contentLanguages()->default() : $sort, $direction);
        }

        $perPage = max(1, min(self::MAX_PER_PAGE, (int) $request->query('per_page', 20)));
        $page = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map(fn (Model $item) => $this->present($item))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function showItem(Request $request, string $resource, int $id): JsonResponse
    {
        $this->useResource($request, $resource, 'read', Permissions::VIEW);

        return response()->json(['data' => $this->present($this->model::with($this->eagerRelations())->findOrFail($id))]);
    }

    public function storeItem(Request $request, string $resource = ''): JsonResponse
    {
        $this->useResource($request, $resource, 'write', Permissions::CREATE);

        $item = $this->saveItem($request, 'create', new $this->model);

        return response()->json(['data' => $this->present($item->fresh($this->eagerRelations()))], 201);
    }

    /**
     * Updates are partial: only the fields sent are validated and written.
     */
    public function updateItem(Request $request, string $resource, int $id): JsonResponse
    {
        $this->useResource($request, $resource, 'write', Permissions::UPDATE);

        $item = $this->saveItem($request, 'edit', $this->findItem($id));

        return response()->json(['data' => $this->present($item->fresh($this->eagerRelations()))]);
    }

    public function destroyItem(Request $request, string $resource, int $id): JsonResponse
    {
        $this->useResource($request, $resource, 'write', Permissions::DELETE);

        $item = $this->findItem($id);

        try {
            if (! $item->canDelete() || ! $this->deleteItem($item)) {
                return response()->json(['message' => 'This record cannot be deleted.'], 409);
            }
        } catch (Refused $refused) {
            return response()->json(['message' => $refused->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    /**
     * The versions of a record, newest (the record as it is) first, each with
     * the values of its form.
     */
    public function listVersions(Request $request, string $resource, int $id): JsonResponse
    {
        $this->useResource($request, $resource, 'read', Permissions::VIEW);

        $versions = $this->revisions()->of($this->findItem($id))->with('activity')->get();

        return response()->json(['data' => $versions->map(static fn (Revision $version): array => [...$version->summary(), 'values' => $version->values])->all()]);
    }

    /**
     * Puts a record back as one of its versions holds it, as the panel's
     * "Restore" does; the values are validated as an update's are.
     */
    public function restoreVersion(Request $request, string $resource, int $id, int $revision): JsonResponse
    {
        $this->useResource($request, $resource, 'write', Permissions::UPDATE);

        $revisions = $this->revisions();
        $item = $this->findItem($id);
        $revisions->restore($item, $revisions->of($item)->findOrFail($revision));

        return response()->json(['data' => $this->present($item->fresh($this->eagerRelations()))]);
    }

    /**
     * The buttons of a module the token may run: server actions on a record
     * and actions over a list of records. Links and filled create forms are
     * the panel's own and are not listed.
     */
    public function listActions(Request $request, string $resource): JsonResponse
    {
        $this->useResource($request, $resource, 'read', Permissions::VIEW);

        $actions = app(ModuleActions::class);
        $usable = fn (Action $action): bool => $actions->allows($request->user(), $action, $this->model)
            && $request->user()->tokenCan($resource.':'.$this->tokenMode($action, $resource));
        $describe = static fn (Action $action): array => [...$action->toArray(), 'permission' => $action->permissionIn($resource)];

        return response()->json(['data' => [
            'record' => array_values(array_map($describe, array_filter($actions->forRecords($this->model), static fn (mixed $action): bool => $action instanceof RecordAction && $usable($action)))),
            'bulk' => array_values(array_map($describe, array_filter($actions->forSelection($this->model), $usable))),
        ]]);
    }

    /**
     * Runs a server action on a record, as its button in the panel does.
     */
    public function runItemAction(Request $request, string $resource, int $id, string $action): JsonResponse
    {
        $this->useResource($request, $resource, 'read', Permissions::VIEW);

        $actions = app(ModuleActions::class);
        $found = $this->usableAction($request, $resource, $actions->find($actions->forRecords($this->model), $action, RecordAction::class, $request->user(), $this->model));

        return $this->actionJson($actions->runRecord($found, $this->findItem($id)));
    }

    /**
     * Runs an action over the records `ids` names: those of them the
     * token's owner may see in the current site.
     */
    public function runBulkItemsAction(Request $request, string $resource, string $action): JsonResponse
    {
        $this->useResource($request, $resource, 'read', Permissions::VIEW);

        $actions = app(ModuleActions::class);
        $found = $this->usableAction($request, $resource, $actions->find($actions->forSelection($this->model), $action, BulkAction::class, $request->user(), $this->model));

        return $this->actionJson($actions->runBulk($found, $this->model, $actions->selectedIds($request)));
    }

    /**
     * File upload into the media library — the same code as in the admin panel.
     * The returned `url` goes into an image field or a gallery list.
     */
    public function upload(Request $request, FileManagerController $files): JsonResponse
    {
        $canWrite = collect($this->resources->abilities())
            ->contains(fn (string $ability): bool => str_ends_with($ability, ':write') && $request->user()->tokenCan($ability));

        abort_unless($canWrite, 403, 'The token is not allowed to change anything.');

        $owner = $request->user();
        abort_unless($owner instanceof AdminUser && app(Permissions::class)->canWriteAnything($owner), 403, 'The token owner may not create or edit anything.');

        return $files->upload($request);
    }

    /**
     * On update a field missing from the request stays as it was, so there is
     * nothing to validate. Otherwise `required` on a name that was not sent
     * would block changing a single price.
     */
    protected function resolveRules(Request $request, string $context, ?Model $record): array
    {
        $rules = parent::resolveRules($request, $context, $record);

        if ($context === 'edit') {
            $rules = array_filter($rules, static fn ($rule, $name): bool => $request->has($name), ARRAY_FILTER_USE_BOTH);
        }

        return $rules;
    }

    /**
     * Two checks: the token was issued for this, and its owner may still do
     * it — a token never gives more than the administrator behind it has
     * today, whatever was ticked when it was issued.
     */
    private function useResource(Request $request, string $resource, string $mode, string $action): void
    {
        $model = $this->resources->model($resource);

        abort_if($model === null, 404, 'No such module. See the list in the documentation: /'.config('atrium.api.prefix', 'api/v1'));
        abort_unless($request->user()->tokenCan("{$resource}:{$mode}"), 403, "The token lacks the {$resource}:{$mode} ability.");

        $owner = $request->user();
        abort_unless($owner instanceof AdminUser && $owner->hasAdminPermission("{$resource}.{$action}"), 403, "The token owner lacks the {$resource}.{$action} permission.");

        $this->model = $model;
    }

    private function revisions(): Revisions
    {
        $revisions = app(Revisions::class);

        abort_unless($revisions->enabledFor($this->model), 404, 'This module keeps no versions of its records.');

        return $revisions;
    }

    /**
     * An action that only reads (its permission is a `view`) needs a read
     * token; any other a write one.
     */
    private function tokenMode(Action $action, string $resource): string
    {
        return str_ends_with($action->permissionIn($resource), '.'.Permissions::VIEW) ? 'read' : 'write';
    }

    /**
     * @template T of Action
     *
     * @param  T  $action
     * @return T
     */
    private function usableAction(Request $request, string $resource, Action $action): Action
    {
        $mode = $this->tokenMode($action, $resource);

        abort_unless($request->user()->tokenCan("{$resource}:{$mode}"), 403, "The token lacks the {$resource}:{$mode} ability.");

        return $action;
    }

    /**
     * 200 with the message, or 422 when the action says it failed.
     */
    private function actionJson(ActionResult $result): JsonResponse
    {
        return response()->json(['message' => $result->message(), 'redirect' => $result->redirectUrl()], $result->isSuccessful() ? 200 : 422);
    }

    /**
     * @return array<int,string>
     */
    private function eagerRelations(): array
    {
        $relations = $this->getBelongsToManyRelations();

        if (method_exists($this->model, 'seoMeta')) {
            $relations[] = 'seoMeta';
        }

        return $relations;
    }

    /**
     * Columns allowed for filtering and sorting: only real model fields, so
     * nothing arbitrary can be slipped in through the URL.
     *
     * @return array<int,string>
     */
    private function filterableColumns(): array
    {
        return array_merge(['id', 'created_at', 'updated_at'], (new $this->model)->getFillable());
    }

    /**
     * The record as the admin form sees it, plus where it lives.
     *
     * @return array<string,mixed>
     */
    private function present(Model $item): array
    {
        $data = $this->prepareItemData($item);
        unset($data['seo_meta'], $data['fullPath']);

        $data['admin_url'] = route($this->model::getAdminRouteName().'.edit', $item->getKey());
        $data['public_url'] = PublicUrl::forRecord($item);

        return $data;
    }
}
