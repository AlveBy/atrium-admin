<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers\Api;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Http\Controllers\FileManagerController;
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
        $this->useResource($request, $resource, 'read');

        $query = $this->model::query()->with($this->eagerRelations());
        $columns = $this->filterableColumns();

        foreach ((array) $request->query('filter', []) as $column => $value) {
            if (in_array($column, $columns, true)) {
                $query->where($column, $value);
            }
        }

        if (($search = (string) $request->query('search', '')) !== '') {
            $searchable = collect($this->model::getAdministrationListColumns())
                ->map(fn ($column) => $column->toArray())
                ->filter(fn (array $column): bool => (bool) ($column['searchable'] ?? false))
                ->pluck('name')
                ->all();

            $query->where(function ($q) use ($searchable, $search) {
                foreach ($searchable as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $sort = (string) $request->query('sort', 'id');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy(in_array($sort, $columns, true) ? $sort : 'id', $direction);

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
        $this->useResource($request, $resource, 'read');

        return response()->json(['data' => $this->present($this->model::with($this->eagerRelations())->findOrFail($id))]);
    }

    public function storeItem(Request $request, string $resource = ''): JsonResponse
    {
        $this->useResource($request, $resource, 'write');

        $item = $this->saveItem($request, 'create', new $this->model);

        return response()->json(['data' => $this->present($item->fresh($this->eagerRelations()))], 201);
    }

    /**
     * Updates are partial: only the fields sent are validated and written.
     */
    public function updateItem(Request $request, string $resource = '', int $id = 0): JsonResponse
    {
        $this->useResource($request, $resource, 'write');

        $item = $this->saveItem($request, 'edit', $this->findItem($id));

        return response()->json(['data' => $this->present($item->fresh($this->eagerRelations()))]);
    }

    public function destroyItem(Request $request, string $resource = '', int $id = 0): JsonResponse
    {
        $this->useResource($request, $resource, 'write');

        $item = $this->findItem($id);

        if (! $item->canDelete() || ! $item->beforeAdminDelete()) {
            return response()->json(['message' => 'This record cannot be deleted.'], 409);
        }

        $item->delete();

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

    private function useResource(Request $request, string $resource, string $mode): void
    {
        $model = $this->resources->model($resource);

        abort_if($model === null, 404, 'No such module. See the list in the documentation: /'.config('atrium.api.prefix', 'api/v1'));
        abort_unless($request->user()->tokenCan("{$resource}:{$mode}"), 403, "The token lacks the {$resource}:{$mode} ability.");

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
