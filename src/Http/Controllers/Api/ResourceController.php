<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers\Api;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Http\Controllers\FileManagerController;
use Atrium\Admin\Models\AdminUser;
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

        foreach ((array) $request->query('filter', []) as $column => $value) {
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

        if (($search = (string) $request->query('search', '')) !== '') {
            $searchable = collect(Hook::apply('atrium.module.columns', $this->model::getAdministrationListColumns(), $this->model))
                ->map(fn ($column) => $column->toArray())
                ->filter(fn (array $column): bool => (bool) ($column['searchable'] ?? false))
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

        $sort = (string) $request->query('sort', 'id');
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

    public function showItem(Request $request, string $resource = '', int $id = 0): JsonResponse
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
    public function updateItem(Request $request, string $resource = '', int $id = 0): JsonResponse
    {
        $this->useResource($request, $resource, 'write', Permissions::UPDATE);

        $item = $this->saveItem($request, 'edit', $this->findItem($id));

        return response()->json(['data' => $this->present($item->fresh($this->eagerRelations()))]);
    }

    public function destroyItem(Request $request, string $resource = '', int $id = 0): JsonResponse
    {
        $this->useResource($request, $resource, 'write', Permissions::DELETE);

        $item = $this->findItem($id);

        if (! $item->canDelete() || ! $this->deleteItem($item)) {
            return response()->json(['message' => 'This record cannot be deleted.'], 409);
        }

        return response()->json(null, 204);
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
