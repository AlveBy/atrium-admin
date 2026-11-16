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
 * Любой модуль админки через API: список, запись, создание, правка, удаление.
 *
 * Наследуется от AdminController ради одного — сохранять теми же правилами,
 * что и форма: та же проверка полей, галочки, привязки, SEO, хуки модели.
 * Модель выбирается по ключу из адреса (`/api/v1/articles`).
 *
 * Имена действий свои (listItems, а не index): родительские index/update
 * отдают страницы Inertia и редиректы, переопределить их JSON-ответом нельзя.
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
     * Правка частичная: проверяются и пишутся только присланные поля.
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
            return response()->json(['message' => 'Эту запись нельзя удалить.'], 409);
        }

        $item->delete();

        return response()->json(null, 204);
    }

    /**
     * Загрузка файла в медиабиблиотеку — тем же кодом, что и в админке.
     * Ответный `url` кладётся в поле-картинку или в список галереи.
     */
    public function upload(Request $request, FileManagerController $files): JsonResponse
    {
        $canWrite = collect($this->resources->abilities())
            ->contains(fn (string $ability): bool => str_ends_with($ability, ':write') && $request->user()->tokenCan($ability));

        abort_unless($canWrite, 403, 'Токену не дано права что-либо менять.');

        return $files->upload($request);
    }

    /**
     * В правке поле, которого нет в запросе, остаётся как было — значит,
     * и проверять его не за чем. Иначе `required` у неприсланного названия
     * не дал бы поменять у тура одну цену.
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

        abort_if($model === null, 404, 'Нет такого модуля. Список — в документации: /'.config('atrium.api.prefix', 'api/v1'));
        abort_unless($request->user()->tokenCan("{$resource}:{$mode}"), 403, "Токену не дано право {$resource}:{$mode}.");

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
     * Колонки, по которым можно фильтровать и сортировать: только настоящие
     * поля модели, чтобы из адреса нельзя было подсунуть что попало.
     *
     * @return array<int,string>
     */
    private function filterableColumns(): array
    {
        return array_merge(['id', 'created_at', 'updated_at'], (new $this->model)->getFillable());
    }

    /**
     * Запись так, как её видит форма админки, плюс где она живёт.
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
