<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Actions\Action;
use Atrium\Admin\Actions\ActionResult;
use Atrium\Admin\Actions\BulkAction;
use Atrium\Admin\Actions\CreateFromRecord;
use Atrium\Admin\Actions\LinkAction;
use Atrium\Admin\Actions\ModuleActions;
use Atrium\Admin\Actions\RecordAction;
use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Columns\Column;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Extensions\Panels;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\DisplayField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Filters\Filter;
use Atrium\Admin\Filters\ModuleFilters;
use Atrium\Admin\History\ActivityLog;
use Atrium\Admin\History\Revisions;
use Atrium\Admin\Hooks\Refused;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Like;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Support\RecordDeleter;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tabs\Tab;
use Atrium\Admin\Tabs\TabRegistry;
use Atrium\Admin\Templates\Templates;
use Atrium\Admin\Traits\BelongsToSite;
use Atrium\Admin\Views\Board;
use Atrium\Admin\Views\KanbanView;
use Atrium\Admin\Views\ModuleViews;
use Atrium\Admin\Views\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

abstract class AdminController extends Controller
{
    /**
     * The model class for this controller.
     *
     * @var class-string<Model&Administrable>
     */
    protected string $model;

    /** Query parameter that preselects a section on the create form (`?section_id=12`). */
    public const SECTION_QUERY = 'section_id';

    /**
     * Per-request memo for contextFields().
     *
     * getAdministrationFields() is already called three times per save, and
     * BelongsToManyField::getOptions() / TreeSelectField::getTreeOptions() hit
     * the database from getFieldProps() every time a field is constructed.
     * Deriving rules must not multiply that.
     *
     * @var array<string,array<int,Field>>
     */
    protected array $contextFieldCache = [];

    /** @var array<int,string>|null */
    private ?array $translatableCache = null;

    /** @var array<string,Field>|null */
    private ?array $fieldsByNameCache = null;

    /**
     * Set while a card is moved on a board: the save then
     * validates only what the move sends, as an update from the API does.
     */
    private bool $moving = false;

    /**
     * Get Inertia page path prefix.
     */
    protected function getPagePrefix(): string
    {
        return 'Admin/Resource';
    }

    /**
     * Props every resource page needs to build its own links.
     *
     * @return array<string,mixed>
     */
    protected function resourceProps(): array
    {
        $can = $this->abilities();

        return [
            'routeName' => $this->model::getAdminRouteName(),
            'createLabel' => $this->model::getAdminCreateLabel(),
            'can' => $can,
            // Whether the list has the import and the export buttons: the module takes part, the role allows it.
            'transfer' => $this->transferAbilities(),
            // Buttons on a record: a filled create form, a link, a server action.
            'actions' => array_map(
                static fn (CreateFromRecord|Action $action): array => $action instanceof Action ? $action->toArray() : ['type' => 'create', ...$action->toArray()],
                $this->visibleRecordActions($can),
            ),
        ];
    }

    /**
     * The record buttons this administrator may use: a filled create form
     * for those who may create here, the others by their own permission.
     *
     * @param  array{view:bool,create:bool,update:bool,delete:bool}  $can
     * @return array<int,CreateFromRecord|Action>
     */
    private function visibleRecordActions(array $can): array
    {
        $actions = app(ModuleActions::class);
        $user = $this->currentAdmin();

        return array_values(array_filter(
            $actions->forRecords($this->model),
            fn (CreateFromRecord|Action $action): bool => $action instanceof CreateFromRecord ? $can[Permissions::CREATE] : $actions->allows($user, $action, $this->model),
        ));
    }

    /**
     * Where the link buttons of each record lead: record id => name => address.
     *
     * @param  iterable<int,Model>  $records
     * @return array<int|string,array<string,string>>
     */
    private function actionUrls(iterable $records): array
    {
        $links = array_filter($this->visibleRecordActions($this->abilities()), static fn (CreateFromRecord|Action $action): bool => $action instanceof LinkAction);
        $urls = [];

        foreach ($records as $record) {
            foreach ($links as $link) {
                $urls[$record->getKey()][$link->getName()] = $link->href($record);
            }
        }

        return $urls;
    }

    /**
     * The bulk buttons of the list this administrator may use. Inertia sends
     * them as their toArray().
     *
     * @return array<int,BulkAction>
     */
    private function visibleBulkActions(): array
    {
        $actions = app(ModuleActions::class);
        $user = $this->currentAdmin();

        return array_values(array_filter($actions->forSelection($this->model), fn (BulkAction $action): bool => $actions->allows($user, $action, $this->model)));
    }

    /**
     * Who is signed in to the panel.
     */
    private function currentAdmin(): mixed
    {
        return auth()->guard(config('atrium.auth.guard', 'admin'))->user();
    }

    /**
     * Whether the administrator may import into the module and export its list.
     *
     * @return array{import:bool,export:bool}
     */
    private function transferAbilities(): array
    {
        $user = auth()->guard(config('atrium.auth.guard', 'admin'))->user();
        $module = $this->model::getAdminRoutePrefix();
        $may = static fn (string $action): bool => Route::has("admin.{$module}.{$action}")
            && (! $user instanceof AdminUser || $user->hasAdminPermission("{$module}.{$action}"));

        return ['import' => $may(Permissions::IMPORT), 'export' => $may(Permissions::EXPORT)];
    }

    /**
     * The module's CreateFromRecord actions, its own and the ones added by
     * the `atrium.module.actions` hook.
     *
     * @return array<int,CreateFromRecord>
     */
    protected function createActions(): array
    {
        return array_filter(
            app(ModuleActions::class)->forRecords($this->model),
            static fn (CreateFromRecord|Action $action): bool => $action instanceof CreateFromRecord,
        );
    }

    /**
     * Runs a server button of a record (RecordAction): 404 for a name the
     * module does not have, 403 for one the administrator may not use.
     */
    public function runAction(int $id, string $action): HttpResponse
    {
        $actions = app(ModuleActions::class);
        $found = $actions->find($actions->forRecords($this->model), $action, RecordAction::class, $this->currentAdmin(), $this->model);

        return $this->actionResponse($actions->runRecord($found, $this->findItem($id)));
    }

    /**
     * Runs a bulk button over the records selected in the list.
     */
    public function runBulkAction(Request $request, string $action): HttpResponse
    {
        $actions = app(ModuleActions::class);
        $found = $actions->find($actions->forSelection($this->model), $action, BulkAction::class, $this->currentAdmin(), $this->model);

        return $this->actionResponse($actions->runBulk($found, $this->model, $actions->selectedIds($request)));
    }

    /**
     * The message as a flash, and back to the page, or on to where the
     * action sends the administrator (a full visit: it may be another site).
     */
    private function actionResponse(ActionResult $result): HttpResponse
    {
        session()->flash($result->isSuccessful() ? 'success' : 'error', $result->message());

        $url = $result->redirectUrl();

        return $url === null ? back() : Inertia::location($url);
    }

    /**
     * Which buttons the page shows: "Create", "Save", "Delete". The routes
     * check the same permissions; this only keeps dead buttons off the page.
     *
     * @return array{view:bool,create:bool,update:bool,delete:bool}
     */
    protected function abilities(): array
    {
        $user = auth()->guard(config('atrium.auth.guard', 'admin'))->user();

        $abilities = $user instanceof AdminUser
            ? app(Permissions::class)->abilities($user, $this->model::getAdminRoutePrefix())
            : array_fill_keys(Permissions::ACTIONS, true);

        foreach ([Permissions::CREATE, Permissions::UPDATE] as $action) {
            $abilities[$action] = $abilities[$action] && $this->moduleAllows($action);
        }

        return $abilities;
    }

    /**
     * Whether the module itself lets records be created or edited here: a
     * module whose records come from elsewhere (form submissions, a log) is
     * only read and deleted. See InteractsWithAdmin::isCreatableInAdmin().
     */
    protected function moduleAllows(string $action): bool
    {
        if ($action === Permissions::CREATE) {
            return ! method_exists($this->model, 'isCreatableInAdmin') || $this->model::isCreatableInAdmin();
        }

        return ! method_exists($this->model, 'isEditableInAdmin') || $this->model::isEditableInAdmin();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        [$query, $columns, $related, $listFilters, $sortBy, $sortDir] = $this->listQuery($request);

        $views = app(ModuleViews::class);
        $declared = $views->for($this->model);
        $view = $declared === [] ? null : $views->choose($declared, $request->query('view'), $this->currentAdmin(), $this->model::getAdminRoutePrefix());

        if ($view !== null) {
            return $this->viewIndex($request, $view, $declared, $query, $listFilters);
        }

        $items = $query->paginate($this->model::getAdminPerPage())->withQueryString();

        // Every row goes through listRow(): a package may present any of them.
        $actionUrls = $this->actionUrls($items->getCollection());
        $computed = array_filter($columns, static fn (object $column): bool => $column instanceof Column && $column->isComputed());
        $items->through(fn (Model $item): array => $this->listRow($item, $related, $computed));

        return Inertia::render($this->getPagePrefix().'/Index', [
            'items' => $items,
            'columns' => $this->serializeColumns($columns),
            'bulkActions' => $this->visibleBulkActions(),
            'actionUrls' => $actionUrls,
            'views' => $views->types($declared),
            'view' => ModuleViews::TABLE,
            ...$this->listPageProps([
                'search' => $request->get('search', ''),
                'sort' => $sortBy,
                'direction' => $sortDir,
                ...$listFilters,
            ]),
        ]);
    }

    /**
     * What every look at the list has: its title, the filters above it, the
     * buttons the module and the other modules add, and what the roles allow.
     *
     * @param  array<string,mixed>  $filters  the state of the search, the sort and the filters
     * @return array<string,mixed>
     */
    private function listPageProps(array $filters): array
    {
        return [
            'filters' => $filters,
            // The filters drawn above the list: the module's and other modules'.
            'filterControls' => app(ModuleFilters::class)->describe($this->model, $this->currentAdmin()),
            'title' => $this->model::getAdminLabel(),
            'links' => Hook::apply('atrium.module.links', [], $this->model),
            ...$this->resourceProps(),
        ];
    }

    /**
     * The list as a kanban board. It is narrowed by the same
     * search and filters as the table, but is not paged or sorted by them.
     *
     * @param  array<string,View>  $declared
     * @param  Builder<Model&Administrable>  $query
     * @param  array<string,string>  $listFilters
     */
    private function viewIndex(Request $request, View $view, array $declared, Builder $query, array $listFilters): Response
    {
        return Inertia::render($this->getPagePrefix().'/Index', [
            'views' => app(ModuleViews::class)->types($declared),
            'view' => $view->type(),
            ...($view instanceof KanbanView ? ['board' => $this->boardProps($view, $query)] : []),
            ...$this->listPageProps(['search' => $request->get('search', ''), ...$listFilters]),
        ]);
    }

    /**
     * The columns of the board with their first cards.
     *
     * @param  Builder<Model&Administrable>  $query
     * @return array<string,mixed>
     */
    private function boardProps(KanbanView $view, Builder $query): array
    {
        $board = $this->board($view);
        $counts = $board->counts($query);
        $lanes = $board->columns();

        // Records no column takes (nothing chosen) get a column of their own, while there are any.
        if ($counts[''] > 0) {
            $lanes[] = ['value' => null, 'label' => __('atrium::views.kanban.no_value')];
        }

        $columns = [];

        foreach ($lanes as $column) {
            $total = $counts[$column['value'] ?? ''] ?? 0;
            $page = $board->page(clone $query, $column['value']);

            $columns[] = [
                ...$column,
                'total' => $total,
                'cards' => $this->presentCards($board, collect($page->items())),
                'more' => $page->hasMorePages(),
            ];
        }

        return ['field' => $view->getField(), 'ordered' => $view->getOrder() !== null, 'columns' => $columns];
    }

    /**
     * More cards of a column (`?column=draft&page=2`, none for the cards of
     * no column), narrowed by the search and filters of the list.
     */
    public function cards(Request $request): JsonResponse
    {
        $view = app(ModuleViews::class)->for($this->model)['kanban'] ?? null;
        abort_unless($view instanceof KanbanView, 404);

        $board = $this->board($view);
        $column = $request->query('column');
        abort_unless($column === null || (is_string($column) && $board->hasColumn($column)), 404);

        [$query] = $this->listQuery($request);
        $page = $board->page($query, $column, $request->integer('page'));

        return response()->json(['cards' => $this->presentCards($board, collect($page->items())), 'more' => $page->hasMorePages()]);
    }

    /**
     * @param  SupportCollection<int,Model>  $records
     * @return array<int,array<string,mixed>>
     */
    private function presentCards(Board $board, SupportCollection $records): array
    {
        $texts = $board->texts($records);
        $titleField = $board->view()->getTitleField();

        return $records->map(fn (Model $record): array => [
            'id' => $record->getKey(),
            'title' => (string) ($titleField === null ? $this->adminTitle($record) : ($this->listRow($record)[$titleField] ?? $this->adminTitle($record))),
            'fields' => $texts[$record->getKey()] ?? [],
            'url' => route($this->model::getAdminRouteName().'.edit', $record->getKey()),
        ])->all();
    }

    /**
     * What a record is called: its own title, as a module without
     * InteractsWithAdmin may have none, else its key.
     */
    private function adminTitle(Model $record): string
    {
        return method_exists($record, 'getAdminTitle') ? $record->getAdminTitle() : (string) $record->getKey();
    }

    private function board(KanbanView $view): Board
    {
        $fields = $this->fieldsByName();
        $cards = [];

        foreach ($view->getCards() as $name) {
            if (isset($fields[$name])) {
                $cards[$name] = $fields[$name];
            }
        }

        return new Board($view, $fields[$view->getField()], $cards);
    }

    /**
     * The form's fields by name, as the form has them (other modules' too).
     *
     * @return array<string,Field>
     */
    private function fieldsByName(): array
    {
        if ($this->fieldsByNameCache === null) {
            $this->fieldsByNameCache = [];

            foreach ($this->allAdminFields() as $field) {
                $this->fieldsByNameCache[$field->getName()] = $field;
            }
        }

        return $this->fieldsByNameCache;
    }

    /**
     * A card dropped in another column of the board: the new column is
     * saved the way the form saves it, so it is checked the same way and
     * heard of by the same hooks. What the view does not write is not taken
     * from the request.
     *
     * `value` is the column, `after` the card now above this one.
     */
    public function move(Request $request, int $id): JsonResponse
    {
        $view = app(ModuleViews::class)->for($this->model)['kanban'] ?? null;
        abort_unless($view instanceof KanbanView, 404);

        $item = $this->findItem($id);
        $board = $this->board($view);
        $order = $view->getOrder();
        $value = $request->input('value');
        $payload = [$view->getField() => $value];
        $shift = [];

        if ($order !== null) {
            $after = $request->input('after');
            $siblings = $board->inColumn($this->model::query()->whereKeyNot($item->getKey()), is_scalar($value) ? (string) $value : null);
            $placed = $board->place($siblings, is_numeric($after) ? (int) $after : null);
            $payload[$order] = $placed['order'];
            $shift = $placed['shift'];
        }

        return response()->json(['card' => $this->presentCards($board, collect([$this->saveMoved($request, $item, $payload, $order, $shift)]))[0]]);
    }

    /**
     * Saves what a move writes, with the cards that make room for it. The
     * save reads the request, so the request is cut down to those fields.
     *
     * @param  array<string,mixed>  $payload
     * @param  array<int,int>  $shift  id => the new value of the order field of the cards that move over
     */
    private function saveMoved(Request $request, Model&Administrable $item, array $payload, ?string $order = null, array $shift = []): Model
    {
        $request->replace($payload);
        $request->query->replace([]);
        $this->moving = true;

        return DB::transaction(function () use ($request, $item, $order, $shift): Model {
            if ($order !== null) {
                foreach ($this->model::query()->whereKey(array_keys($shift))->get() as $sibling) {
                    $sibling->forceFill([$order => $shift[$sibling->getKey()]])->save();
                }
            }

            return $this->saveItem($request, 'edit', $item);
        });
    }

    /**
     * The records of the list as the request narrows it: the module's
     * filters, the search over its searchable columns and the sort. The list
     * screen pages it; an export reads all of it.
     *
     * @return array{0:Builder<Model&Administrable>,1:array<int,object>,2:array<int,string>,3:array<string,string>,4:string,5:string} query, columns, related columns, filters, sort column, direction
     */
    protected function listQuery(Request $request): array
    {
        $query = (new $this->model)->scopeAdminList($this->model::query());
        $filters = app(ModuleFilters::class);
        $user = $this->currentAdmin();
        $columns = array_values(array_filter(
            Hook::apply('atrium.module.columns', $this->model::getAdministrationListColumns(), $this->model),
            // A column that is no Column (only toArray()) needs no permission.
            fn (object $column): bool => ! $column instanceof Column || $filters->allows($user, $column->permissionIn($this->model::getAdminRoutePrefix())),
        ));
        $related = $this->relatedColumns($columns);
        $listFilters = $this->listFilters($request);
        $defined = $filters->for($this->model, $user);

        foreach ($listFilters as $name => $value) {
            if ($defined[$name] instanceof Filter) {
                $defined[$name]->apply($query, $value);
            } elseif (! Hook::apply('atrium.query.filter', false, $query, $name, $value)) {
                $query->where($name, $value);
            }
        }

        // A column like `brand.name` shows a related record's attribute.
        if ($related !== []) {
            $query->with(array_map(static fn (string $name): string => Str::beforeLast($name, '.'), $related));
        }

        // Apply search
        if ($search = $request->get('search')) {
            // Column::$searchable and $name are protected, so reading them as
            // properties yields null (the ?? swallows the visibility error) and
            // the filter matched nothing — search silently returned every row.
            $searchable = collect($columns)
                ->map(fn ($col) => $col->toArray())
                ->where('searchable', true)
                ->pluck('name')
                ->filter(fn ($name): bool => is_string($name) && $name !== '' && ! in_array($name, $related, true))
                ->values()
                ->toArray();

            if (! empty($searchable)) {
                $codes = $this->contentLanguages()->codes();

                $query->where(function ($q) use ($searchable, $search, $codes) {
                    foreach ($searchable as $column) {
                        if (Hook::apply('atrium.query.search', false, $q, $column, $search)) {
                            continue;
                        }

                        // A translatable column is JSON: search every language.
                        if ($this->isTranslatable($column)) {
                            $q->orWhere(fn ($w) => $w->whereTranslationLike($column, $search, $codes));
                        } else {
                            Like::contains($q, $column, $search, 'or');
                        }
                    }
                });
            }
        }

        // Apply sorting
        $sortBy = $request->get('sort', $this->model::getDefaultSortColumn());

        if (in_array($sortBy, $related, true)) {
            $sortBy = $this->model::getDefaultSortColumn();
        }
        $sortDir = $request->get('direction', $this->model::getDefaultSortDirection());

        if (! Hook::apply('atrium.query.sort', false, $query, $sortBy, $sortDir === 'desc' ? 'desc' : 'asc')) {
            $query->orderBy($this->isTranslatable($sortBy) ? $sortBy.'->'.$this->contentLanguages()->default() : $sortBy, $sortDir);
        }

        return [$query, $columns, $related, $listFilters, $sortBy, $sortDir];
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        abort_unless($this->moduleAllows(Permissions::CREATE), 403);

        $data = $this->prefillFromActions($request, $this->preselectSection($request, $this->prepareFormData('create')));
        $data = $this->withFieldDefaults($data, $this->listFilters($request));

        return Inertia::render($this->getPagePrefix().'/Create', [...$data, ...$this->resourceProps()]);
    }

    /**
     * The list filters in the query string (`?module=…`, `?brand=4`): only
     * those of ModuleFilters, with a value a Filter accepts. The list is
     * narrowed by them and the create form opened from it starts with the
     * same values.
     *
     * @return array<string,string>
     */
    protected function listFilters(Request $request): array
    {
        $filters = [];

        foreach (app(ModuleFilters::class)->for($this->model, $this->currentAdmin()) as $name => $filter) {
            $value = $request->query((string) $name);
            $value = $filter instanceof Filter ? $filter->value($value) : (is_string($value) && $value !== '' ? $value : null);

            if ($value !== null) {
                $filters[$name] = $value;
            }
        }

        return $filters;
    }

    /**
     * Preselect the section the editor came from.
     *
     * The public admin bar links to this form straight from an open section of
     * the site (`?section_id=12`) — the section must already be chosen,
     * otherwise an editor who clicked "Add item" on a section has to find it
     * again in the tree and misses half the time.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function preselectSection(Request $request, array $data): array
    {
        $sectionId = (int) $request->query(self::SECTION_QUERY);

        if ($sectionId <= 0 || ! $this->sectionExists($sectionId)) {
            return $data;
        }

        return $this->withFieldDefaults($data, [self::SECTION_QUERY => $sectionId]);
    }

    /**
     * The fields a CreateFromRecord action fills, from the link it opened
     * (`?parent_id=12`). Only those fields: the query string sets nothing
     * else on the form.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prefillFromActions(Request $request, array $data): array
    {
        $defaults = [];

        foreach ($this->createActions() as $action) {
            foreach (array_keys($action->getFill()) as $field) {
                $value = $request->query($field);

                if (is_string($value) && $value !== '') {
                    $defaults[$field] = ctype_digit($value) ? (int) $value : $value;
                }
            }
        }

        return $this->withFieldDefaults($data, $defaults);
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<string,mixed>  $defaults  field name => value
     * @return array<string,mixed>
     */
    private function withFieldDefaults(array $data, array $defaults): array
    {
        $preset = static function (array $field) use ($defaults): array {
            if (array_key_exists($field['name'] ?? '', $defaults)) {
                $field['default'] = $defaults[$field['name']];
            }

            return $field;
        };

        if (isset($data['fields']) && is_array($data['fields'])) {
            $data['fields'] = array_map($preset, $data['fields']);
        }

        if (isset($data['fieldsByTab']) && is_array($data['fieldsByTab'])) {
            $data['fieldsByTab'] = array_map(
                static fn (array $fields): array => array_map($preset, $fields),
                $data['fieldsByTab']
            );
        }

        return $data;
    }

    /**
     * Whether the section really exists: for a broken link it is better to open
     * the form with no section chosen than with a non-existent one.
     */
    protected function sectionExists(int $sectionId): bool
    {
        $model = new $this->model;

        if (! method_exists($model, 'section')) {
            return false;
        }

        return $model->section()->getRelated()->newQuery()->whereKey($sectionId)->exists();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->saveItem($request, 'create', new $this->model);

        return redirect()
            ->route($this->model::getAdminRouteName().'.index')
            ->with('success', __('atrium::resource.created'));
    }

    /**
     * Validate the request and write it to $item — one path for the form and
     * for the API, so a record saved by an agent is saved exactly like one
     * saved by hand. The record, its relations and its SEO are saved whole or
     * not at all; `atrium.record.saved` is heard after the commit only.
     *
     * ignoreCurrentRecord() inside resolveRules() handles unique-ignore on
     * edit for every attribute, not just `email`.
     */
    protected function saveItem(Request $request, string $context, Model&Administrable $item): Model&Administrable
    {
        abort_unless($this->moduleAllows($context === 'create' ? Permissions::CREATE : Permissions::UPDATE), 403);

        $record = $context === 'create' ? null : $item;

        $this->normalizeTranslatableInput($request);

        $this->runValidation($request, $this->resolveRules($request, $context, $record), $context);

        $validated = $this->submittedData($request, $context, $item);

        // A record's site is the site the request works with (BelongsToSite),
        // never one the client names: a sent site_id would move it to a site
        // its administrator may not even see.
        unset($validated['site_id']);

        // Hash password if present; on edit an empty one means "keep".
        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } elseif ($context === 'edit') {
            unset($validated['password']);
        }

        $validated = $item->transformAdminData($validated);
        // The template picked is held by an `atrium.record.data` listener and
        // written with the record; nothing is left over from a refused save.
        $templates = app(Templates::class);
        $templates->hold(null);
        $validated = $this->filterRecordData($request, $validated, $item, $context);
        $template = $templates->take();

        // BelongsToMany values live in a pivot table, not a column. They must
        // be pulled out before fill(), or the insert fails with
        // "Unknown column 'tizers' in 'field list'" when the relation name also
        // appears in $fillable.
        $relations = $this->extractRelationValues($validated);
        $seo = $this->extractSeoValues($validated);

        $history = app(ActivityLog::class);
        $logged = $history->enabledFor($this->model);
        $fields = $this->contextFields($context);

        $saved = DB::transaction(function () use ($item, $validated, $relations, $seo, $templates, $template, $history, $logged, $fields): Model {
            // A retry after a deadlock starts again from the record as it
            // came: a rolled back insert leaves the model thinking it exists.
            $record = clone $item;
            $before = $logged && $record->exists ? $this->snapshot($record, $fields) : [];

            // The save logs itself below; a button that saves through here
            // must not log it a second time.
            $history->quietly(function () use ($record, $validated, $relations, $seo, $templates, $template): void {
                $record->fill($validated);
                $record->save();

                $this->syncRelations($record, $relations);
                $this->persistSeoValues($record, $seo);

                // Before the history reads the record as it is now.
                if ($template !== null) {
                    $templates->pick($record, $template);
                }

                $record->afterAdminSave($validated);
            });

            if ($logged) {
                $this->logSave($history, $record, $fields, $before, $validated);
            }

            return $record;
        }, attempts: 3);

        // Pivot syncs and SEO rows fire no event of the record itself.
        DB::afterCommit(static fn () => app(RecordCache::class)->changed($saved));

        // Listeners send mail and queue jobs: only for what was committed,
        // after the outermost transaction if the caller opened one.
        DB::afterCommit(static fn () => Hook::fire('atrium.record.saved', $saved, $context));

        return $saved;
    }

    /**
     * Writes what the save changed to the activity log: a new record with the
     * values it was given, a changed one with the fields that differ, and
     * keeps the record as it is now as a version. A save that changed nothing
     * leaves neither.
     *
     * @param  array<int,Field>  $fields  the fields of the form that was saved
     * @param  array<string,mixed>  $before  snapshot() before the save; empty for a new record
     * @param  array<string,mixed>  $validated
     */
    private function logSave(ActivityLog $history, Model&Administrable $record, array $fields, array $before, array $validated): void
    {
        $secrets = array_filter(
            $this->secretFields($record, $fields),
            static fn (string $name): bool => filled($validated[$name] ?? null),
        );

        $after = $this->snapshot($record, $fields);
        $changes = $history->changes($before, $after, $secrets);

        if (! $record->wasRecentlyCreated && $changes === []) {
            return;
        }

        $entry = $history->record($record, $record->wasRecentlyCreated ? ActivityLog::CREATED : ActivityLog::UPDATED, $changes);
        $revisions = app(Revisions::class);

        if ($revisions->enabledFor($record::class)) {
            $revisions->keep($record, $entry, $before, $after);
        }
    }

    /**
     * The record as its form shows it, field by field: what the activity log
     * compares. Read-only fields and secret ones (passwords, keys) are left
     * out.
     *
     * @param  array<int,Field>  $fields
     * @return array<string,mixed>
     */
    protected function snapshot(Model $record, array $fields): array
    {
        $record->load($this->getBelongsToManyRelations());

        $secrets = $this->secretFields($record, $fields);
        $tracked = array_filter(
            $fields,
            static fn (Field $field): bool => ! $field instanceof DisplayField && ! in_array($field->getName(), $secrets, true),
        );

        return array_intersect_key(
            $this->presentItem($record, $tracked),
            array_flip(array_map(static fn (Field $field): string => $field->getName(), $tracked)),
        );
    }

    /**
     * Those of $fields that hold a password, a key or a token: a password
     * input, an attribute the model hides, or a name that says so
     * (`atrium.history.secrets`).
     *
     * @param  array<int,Field>  $fields
     * @return array<int,string>
     */
    protected function secretFields(Model $record, array $fields): array
    {
        $secrets = [];

        foreach ($fields as $field) {
            if (($field instanceof TextField && $field->isPassword()) || ActivityLog::isSecretName($field->getName(), $record)) {
                $secrets[] = $field->getName();
            }
        }

        return $secrets;
    }

    /**
     * `atrium.record.data`: other modules change what is saved, or refuse it
     * with Refused. A refusal is a validation error, so the form keeps what
     * was typed and the API answers 422 with the message; one without a
     * field is also shown above the form.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function filterRecordData(Request $request, array $data, Model $item, string $context): array
    {
        try {
            return Hook::apply('atrium.record.data', $data, $item, $context);
        } catch (Refused $refused) {
            if ($refused->field === null && $request->hasSession()) {
                $request->session()->flash('error', $refused->getMessage());
            }

            throw ValidationException::withMessages([$refused->field ?? '_record' => $refused->getMessage()]);
        }
    }

    /**
     * The fields that are actually part of the submitted form for $context.
     *
     * getAdminFieldsWithTabs() rather than getAdministrationFields() so the SEO
     * fields injected by HasAdminSeo — which the form does render and does
     * submit — are validated and reach afterAdminSave() too.
     *
     * @return array<int,Field>
     */
    protected function contextFields(string $context): array
    {
        if (isset($this->contextFieldCache[$context])) {
            return $this->contextFieldCache[$context];
        }

        $fields = $this->allAdminFields();

        $fields = array_values(array_filter(
            $fields,
            static fn ($field): bool => $context === 'create'
                ? $field->shouldShowOnCreate()
                : $field->shouldShowOnEdit()
        ));

        return $this->contextFieldCache[$context] = $fields;
    }

    /**
     * Build the validation rules for a save.
     *
     * An explicit getCreateValidationRules()/getUpdateValidationRules() on the
     * model still wins outright (AdminUser relies on this for `confirmed`).
     * Otherwise the rules are derived from the field declarations, so all
     * resources get validation without per-model code.
     */
    protected function resolveRules(Request $request, string $context, ?Model $record): array
    {
        $explicit = $context === 'create'
            ? $this->model::getCreateValidationRules()
            : $this->model::getUpdateValidationRules();

        if (! empty($explicit)) {
            return $this->onlySent($request, $this->ignoreCurrentRecord($explicit, $record));
        }

        $rules = [];

        foreach ($this->contextFields($context) as $field) {
            $name = $field->getName();

            if ($this->isTranslatable($name)) {
                $rules = [...$rules, ...$this->translatableRules($field, $request, $context, $record)];

                continue;
            }

            // input() does not see uploads, and ImageField/FileField need to
            // know whether this is a real file or the stored path.
            $value = $request->file($name) ?? $request->input($name);

            $rules[$name] = $field->getValidationRules(
                new FieldContext($context, $record, $value, $request->has($name))
            );
        }

        return $this->onlySent($request, $this->ignoreCurrentRecord($rules, $record));
    }

    /**
     * While a card is moved, a field the move does not send
     * stays as it was, so there is nothing to validate: `required` on a name
     * that was not sent must not block a move.
     *
     * @param  array<string,mixed>  $rules
     * @return array<string,mixed>
     */
    private function onlySent(Request $request, array $rules): array
    {
        return $this->moving ? array_filter($rules, static fn (mixed $rule, string $name): bool => $request->has($name), ARRAY_FILTER_USE_BOTH) : $rules;
    }

    /**
     * Human-readable :attribute names, taken from the field labels.
     */
    protected function validationAttributes(string $context): array
    {
        $attributes = [];

        foreach ($this->contextFields($context) as $field) {
            // getLabel(), not toArray()['label']: toArray() runs getFieldProps(),
            // which queries the database for belongsToMany / treeSelect fields.
            $label = $field->getLabel();

            if ($label === '') {
                continue;
            }

            // "The name field is required." reads right in lower case; in
            // German or Russian a label keeps its own case (nouns, quotes).
            $label = str_starts_with(app()->getLocale(), 'en') ? mb_strtolower($label) : $label;
            $name = $field->getName();
            $attributes[$name] = $label;

            if ($this->isTranslatable($name)) {
                $languages = $this->contentLanguages();

                foreach ($languages->all() as $language) {
                    // "Title (Deutsch)": with several languages the editor has
                    // to know which tab the error is on.
                    $attributes["{$name}.{$language->code}"] = $languages->isMultilingual()
                        ? "{$label} ({$language->name})"
                        : $label;
                }
            }
        }

        return $attributes;
    }

    /**
     * Run the validator. Failures raise ValidationException, which Laravel
     * turns into a redirect back with the errors bag — exactly what the Inertia
     * forms already render as p.text-red-600 and count per tab.
     *
     * The return value is deliberately unused: see submittedData().
     */
    protected function runValidation(Request $request, array $rules, string $context): void
    {
        $request->validate($rules, [], $this->validationAttributes($context));
    }

    /**
     * The payload to hand to the model.
     *
     * CRITICAL: this is built from the request, NOT from validate()'s return
     * value. validate() only gives back the keys that carry a rule, so using it
     * as the payload would silently stop saving every fillable column that has
     * no admin field — active_from / active_to on 28 models, depth /
     * left_margin / right_margin on the section models, profit, content_raw,
     * page_type, source_url, scraped_at. Validation is a gate, never a filter.
     *
     * The key set is a strict superset of the old $request->only($fillable):
     * fillable columns, plus the names of the fields shown in this context.
     * The field names are what extractRelationValues() needs (belongsToMany
     * relation names such as profits/linkGoods are not in $fillable, so
     * only($fillable) used to drop them and the pivots never synced), and what
     * carries the HasAdminSeo fields through to afterAdminSave(). only()
     * returns just the keys actually present, so nothing is invented and no
     * column gets null-overwritten; fill() still enforces $fillable.
     */
    protected function submittedData(Request $request, string $context, Model $item): array
    {
        $keys = $item->getFillable();

        foreach ($this->contextFields($context) as $field) {
            // Read-only HTML drawn from the record: nothing to take back.
            if (! $field instanceof DisplayField) {
                $keys[] = $field->getName();
            }
        }

        $data = $request->only($keys);

        // An untouched checkbox arrives as an empty string: the form seeds every
        // field as `default ?? ''`, and ConvertEmptyStringsToNull turns the
        // empty string into null. `nullable|boolean` lets that through, and
        // null reaches the column — a `boolean NOT NULL default false` column
        // then fails the whole record with "Column '…' cannot be null". So a
        // record would not save until someone touched the checkbox.
        //
        // An unchecked box is false (or the absence of the Bitrix code), not
        // "unknown". How to store it is up to CheckboxField itself: a boolean
        // or a Bitrix code string ('YES').
        foreach ($this->contextFields($context) as $field) {
            $name = $field->getName();

            if ($field instanceof CheckboxField && array_key_exists($name, $data)) {
                $data[$name] = $field->transformValue($data[$name]);
            }
        }

        // Translations of languages that are not active (or not there at all)
        // are not taken from the request; the stored ones stay untouched.
        $codes = array_flip($this->contentLanguages()->codes());

        foreach ($this->translatableAttributes() as $name) {
            if (! is_array($data[$name] ?? null)) {
                continue;
            }

            $data[$name] = array_intersect_key($data[$name], $codes);

            if ($data[$name] === []) {
                unset($data[$name]);
            }
        }

        // Pure form artefact: it drives the `confirmed` rule and nothing else.
        unset($data['password_confirmation']);

        return $data;
    }

    /**
     * Make unique rules exempt the row being edited.
     *
     * Replaces an email-only hack that appended ",{$id}" to the rule string.
     * That was wrong for the common `unique:table` form (no column), where it
     * produced `unique:admin_users,5` — i.e. a column literally named "5".
     */
    protected function ignoreCurrentRecord(array $rules, ?Model $record): array
    {
        if (($record === null || ! $record->exists) && ! $this->isPerSite()) {
            return $rules;
        }

        foreach ($rules as $attribute => $attributeRules) {
            $normalized = is_array($attributeRules)
                ? $attributeRules
                : explode('|', (string) $attributeRules);

            $changed = false;

            foreach ($normalized as $index => $rule) {
                if (! is_string($rule) || ! str_starts_with($rule, 'unique:')) {
                    continue;
                }

                $parameters = explode(',', substr($rule, strlen('unique:')));

                // unique:table,column,ignore[,idColumn] — already scoped.
                if (count($parameters) >= 3) {
                    continue;
                }

                $table = $parameters[0];
                $column = $parameters[1] ?? '';

                if ($column === '' || strtoupper($column) === 'NULL') {
                    $column = $attribute;
                }

                $normalized[$index] = $this->uniqueRule($table, $column, $record);

                $changed = true;
            }

            if ($changed) {
                $rules[$attribute] = $normalized;
            }
        }

        return $rules;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): Response
    {
        $item = $this->model::findOrFail($id);

        // Load BelongsToMany relations for proper data transformation
        $item->load($this->getBelongsToManyRelations());

        $data = $this->prepareFormData('edit', $item);

        $admin = $this->currentAdmin();

        return Inertia::render($this->getPagePrefix().'/Edit', [
            ...$data,
            'actionUrls' => $this->actionUrls([$item]),
            'historyUrl' => HistoryController::urlFor($this->model::getAdminRouteName(), $id),
            // Blocks other modules draw on the record (atrium.ui.panels).
            'panels' => app(Panels::class)->for($item, $admin instanceof AdminUser ? $admin : null),
            ...$this->resourceProps(),
        ]);
    }

    /**
     * Pull belongsToMany values out of the submitted data (by reference).
     *
     * Their values belong in a pivot table; leaving them in would either be
     * silently ignored or, when the relation name is also in $fillable, blow
     * up the INSERT with an unknown-column error.
     *
     * @return array<string,array<int,mixed>> relation method => ids
     */
    protected function extractRelationValues(array &$data): array
    {
        $relations = [];

        foreach ($this->model::getAdministrationFields() as $field) {
            // getType(), not toArray(): toArray() would load every record
            // the field offers just to read its type.
            if ($field->getType() !== 'belongsToMany') {
                continue;
            }

            $name = $field->getName();

            if (! array_key_exists($name, $data)) {
                continue;
            }

            $value = $data[$name];
            unset($data[$name]);

            $method = Str::camel($name);

            if (! method_exists($this->model, $method)) {
                continue;
            }

            $relations[$method] = array_values(array_filter(
                (array) $value,
                static fn ($id): bool => $id !== null && $id !== ''
            ));
        }

        return $relations;
    }

    /**
     * Persist the pivot rows for belongsToMany fields.
     *
     * Without this, every belongsToMany field in the panel renders, accepts
     * input and reports success while saving nothing.
     */
    protected function syncRelations(Model $item, array $relations): void
    {
        foreach ($relations as $method => $ids) {
            $relation = $item->{$method}();

            if ($relation instanceof BelongsToMany) {
                $relation->sync($ids);
            }
        }
    }

    /**
     * Get list of BelongsToMany relation names from model fields.
     */
    protected function getBelongsToManyRelations(): array
    {
        $relations = [];
        $fields = $this->model::getAdministrationFields();

        foreach ($fields as $field) {
            // snake_case field name, camelCase relation method; a field with
            // no relation behind it is left out, as extractRelationValues() does.
            $method = Str::camel($field->getName());

            if ($field->getType() === 'belongsToMany' && method_exists($this->model, $method)) {
                $relations[] = $method;
            }
        }

        return $relations;
    }

    protected function findItem(int $id): Model&Administrable
    {
        return $this->model::query()->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->saveItem($request, 'edit', $this->findItem($id));

        return redirect()
            ->route($this->model::getAdminRouteName().'.index')
            ->with('success', __('atrium::resource.updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $item = $this->findItem($id);

        if (! $item->canDelete()) {
            return back()->with('error', __('atrium::resource.cannot_delete'));
        }

        try {
            if (! $this->deleteItem($item)) {
                return back()->with('error', __('atrium::resource.delete_failed'));
            }
        } catch (Refused $refused) {
            return back()->with('error', $refused->getMessage());
        }

        return redirect()
            ->route($this->model::getAdminRouteName().'.index')
            ->with('success', __('atrium::resource.deleted'));
    }

    /**
     * Delete a record with what beforeAdminDelete() takes with it, whole or not
     * at all. False when beforeAdminDelete() refuses; Refused when a listener
     * of `atrium.record.deleting` does. `atrium.record.deleted` is heard after
     * the commit only.
     */
    protected function deleteItem(Model&Administrable $item): bool
    {
        return app(RecordDeleter::class)->delete($item);
    }

    /**
     * Prepare form data with tabs support.
     */
    protected function prepareFormData(string $context, $item = null): array
    {
        $title = $item
            ? __('atrium::resource.title.edit', ['title' => $item->getAdminTitle()])
            : __('atrium::resource.title.create', ['label' => $this->model::getAdminLabel()]);

        $adminLabel = $this->model::getAdminLabel();

        // Check if model supports tabs
        if (method_exists($this->model, 'getFieldsGroupedByTabs') && method_exists($this->model, 'usesTabs') && $this->model::usesTabs()) {
            $tabsData = $this->model::getFieldsGroupedByTabs($context);

            return [
                'tabs' => $tabsData['tabs'],
                'fieldsByTab' => $this->serializeFieldsByTab(array_map(fn (array $fields): array => $this->withRelationSearch($fields, $item), $tabsData['fields']), $context),
                'fields' => $this->serializeFields(
                    $this->withRelationSearch($this->model::getAdminFieldsWithTabs(), $item),
                    $context
                ), // For backward compatibility
                'item' => $this->prepareItemData($item),
                'itemTitle' => $item?->getAdminTitle(),
                'title' => $title,
                'adminLabel' => $adminLabel,
                'useTabs' => true,
            ];
        }

        // Fallback: old logic without tabs
        $fields = $this->withRelationSearch($this->allAdminFields(), $item);

        // A module without tabs gets them once another module adds a tab
        // that has fields: the module's own fields stay on the first tab.
        $added = Hook::apply('atrium.module.tabs', [], $this->model);
        $shown = array_filter($fields, static fn ($field): bool => $context === 'create' ? $field->shouldShowOnCreate() : $field->shouldShowOnEdit());
        $names = array_map(static fn ($tab): string => $tab instanceof Tab ? $tab->getName() : $tab['name'], $added);
        $grouped = [];

        foreach ($shown as $field) {
            $grouped[in_array($field->getTab(), $names, true) ? $field->getTab() : 'params'][] = $field;
        }

        $tabs = TabRegistry::sortTabs(TabRegistry::filterEmptyTabs([Tab::make('params')->label(__('atrium::fields.tabs.main')), ...$added], $grouped));

        if (count($tabs) > 1) {
            return [
                'tabs' => array_map(static fn ($tab): array => $tab instanceof Tab ? $tab->toArray() : $tab, $tabs),
                'fieldsByTab' => $this->serializeFieldsByTab($grouped, $context),
                'fields' => $this->serializeFields($fields, $context),
                'item' => $this->prepareItemData($item),
                'itemTitle' => $item?->getAdminTitle(),
                'title' => $title,
                'adminLabel' => $adminLabel,
                'useTabs' => true,
            ];
        }

        return [
            'fields' => $this->serializeFields($fields, $context),
            'item' => $this->prepareItemData($item),
            'itemTitle' => $item?->getAdminTitle(),
            'title' => $title,
            'adminLabel' => $adminLabel,
            'useTabs' => false,
        ];
    }

    /**
     * Relation fields with more records than fit in the page search them
     * through the module's options route, and name the ones the record
     * already links to. A module on routes of its own keeps the full lists.
     *
     * @param  array<int,mixed>  $fields
     * @return array<int,mixed>
     */
    protected function withRelationSearch(array $fields, ?Model $item): array
    {
        foreach ($fields as $field) {
            if (! $field instanceof BelongsToField) {
                continue;
            }

            $route = $this->model::getAdminRouteName().'.options';

            if (Route::has($route)) {
                $field->searchUrl(route($route, ['field' => $field->getName()], false))->record($item);
            }
        }

        return $fields;
    }

    /**
     * Prepare item data with additional computed fields.
     */
    /**
     * Every field the form renders, including the SEO block.
     *
     * HasAdminSeo gives 31 models getSeoAdminFields() and an updateSeoMeta(),
     * and every model declares a `seo` tab — but nothing ever merged the two,
     * so the SEO tab rendered "No fields to show" everywhere and no
     * meta was ever saved. HasAdminTabs::getAdminFieldsWithTabs() does the
     * merge but is only used by 3 of the 32 models, so do it here instead.
     *
     * @return array<int,Field>
     */
    protected function allAdminFields(): array
    {
        return static::fieldsOf($this->model);
    }

    /**
     * allAdminFields() for any model — the API documentation describes
     * exactly the fields the form would accept.
     *
     * @param  class-string  $model
     * @return array<int,Field>
     */
    public static function fieldsOf(string $model): array
    {
        if (method_exists($model, 'getAdminFieldsWithTabs')) {
            return $model::getAdminFieldsWithTabs();
        }

        $fields = $model::getAdministrationFields();

        if (method_exists($model, 'getSeoAdminFields')) {
            foreach ($model::getSeoAdminFields() as $seoField) {
                $fields[] = $seoField->tab('seo');
            }
        }

        return Hook::apply('atrium.module.fields', $fields, $model);
    }

    /**
     * Form field name => seo_meta column.
     *
     * The map comes from the model itself (HasAdminSeo::seoFieldMap()) rather
     * than a second list kept here: copies that drifted apart once cost us
     * eight fields the form showed but the check counted as unsaved.
     *
     * @return array<string,string>
     */
    protected function seoFieldMap(): array
    {
        if (! method_exists($this->model, 'seoFieldMap')) {
            return [];
        }

        return $this->model::seoFieldMap();
    }

    /**
     * Pull the SEO values out of the payload (by reference) — they live in the
     * polymorphic seo_meta table, not on the model's own columns.
     *
     * @return array<string,mixed> seo_meta column => value
     */
    protected function extractSeoValues(array &$data): array
    {
        $seo = [];

        foreach ($this->seoFieldMap() as $fieldName => $column) {
            if (! array_key_exists($fieldName, $data)) {
                continue;
            }

            $seo[$column] = $data[$fieldName];
            unset($data[$fieldName]);
        }

        return $seo;
    }

    protected function persistSeoValues(Model $item, array $seo): void
    {
        if ($seo === [] || ! method_exists($item, 'updateSeoMeta') || ! method_exists($item, 'seoMeta')) {
            return;
        }

        // Nothing filled in and nothing stored yet: do not create an empty row.
        if (! $item->seoMeta()->exists() && count(array_filter($seo, static fn ($v): bool => $v !== null && $v !== '')) === 0) {
            return;
        }

        $item->updateSeoMeta($seo);
    }

    protected function prepareItemData(?Model $item): ?array
    {
        if (! $item) {
            return null;
        }

        return $this->presentItem($item, $this->allAdminFields());
    }

    /**
     * The values of a record as the form of $fields shows them.
     *
     * @param  array<int,Field>  $fields
     * @return array<string,mixed>
     */
    private function presentItem(Model $item, array $fields): array
    {
        $data = $item->toArray();

        // Transform BelongsToMany relations to arrays of IDs
        // Laravel's toArray() returns full objects, but Vue components need simple ID arrays
        foreach ($item->getRelations() as $name => $relation) {
            if ($relation instanceof Collection) {
                $data[$name] = $relation->pluck('id')->toArray();
            }
        }

        // Read-only HTML fields are drawn from the record here.
        foreach ($fields as $field) {
            if ($field instanceof DisplayField) {
                $data[$field->getName()] = $field->render($item);
            }
        }

        // Add fullPath for URL generation in SlugField
        if (method_exists($item, 'getFullPath')) {
            $data['fullPath'] = $item->getFullPath();
        }

        // Seed the SEO inputs from the polymorphic seo_meta row.
        if (method_exists($item, 'seoMeta')) {
            $meta = $item->getRelationValue('seoMeta');

            foreach ($this->seoFieldMap() as $fieldName => $column) {
                $data[$fieldName] = $meta->{$column} ?? null;
            }
        }

        return Hook::apply('atrium.record.present', $data, $item, 'form');
    }

    /**
     * Serialize fields grouped by tabs.
     */
    protected function serializeFieldsByTab(array $fieldsByTab, string $context): array
    {
        $result = [];

        foreach ($fieldsByTab as $tabName => $fields) {
            $result[$tabName] = $this->serializeFields($fields, $context);
        }

        return $result;
    }

    /**
     * Serialize fields for frontend.
     */
    protected function serializeFields(array $fields, string $context = 'create'): array
    {
        return collect($fields)
            ->filter(function ($field) use ($context) {
                if ($context === 'create') {
                    return $field->shouldShowOnCreate();
                }

                return $field->shouldShowOnEdit();
            })
            ->map(fn ($field) => $field->toArray())
            // The preview under the slug field must show the public site address,
            // not the admin slug path: /countries/china for a country, not
            // /china. The field does not know its model, so the controller
            // supplies the address map.
            ->map(fn (array $field) => PublicUrl::decorateSlugField($field, $this->model))
            // The form shows a language switch for these.
            ->map(fn (array $field) => $this->isTranslatable((string) ($field['name'] ?? ''))
                ? [...$field, 'translatable' => true]
                : $field)
            ->values()
            ->toArray();
    }

    /**
     * The model's translatable columns (Atrium\Admin\Traits\Translatable).
     *
     * @return array<int,string>
     */
    protected function translatableAttributes(): array
    {
        if ($this->translatableCache === null) {
            $model = new $this->model;

            $this->translatableCache = Hook::apply(
                'atrium.module.translatable',
                method_exists($model, 'getTranslatableAttributes') ? $model->getTranslatableAttributes() : [],
                $this->model,
            );
        }

        return $this->translatableCache;
    }

    protected function isTranslatable(string $name): bool
    {
        return in_array($name, $this->translatableAttributes(), true);
    }

    protected function contentLanguages(): ContentLanguages
    {
        return app(ContentLanguages::class);
    }

    /**
     * The form sends a translatable field as {"en": "…", "de": "…"}. A plain
     * value — from an API client that does not care about languages — is the
     * default language's text.
     */
    protected function normalizeTranslatableInput(Request $request): void
    {
        $default = $this->contentLanguages()->default();

        foreach ($this->translatableAttributes() as $name) {
            if ($request->has($name) && ! is_array($request->input($name))) {
                $request->merge([$name => [$default => $request->input($name)]]);
            }
        }
    }

    /**
     * Rules for one translatable field, per language.
     *
     * The default language gets the field's rules as declared; it is what
     * every other language falls back to, so a required field is required
     * there. Other languages may stay empty. A unique rule is checked within
     * its own language: "/de/news/hallo" and "/en/news/hallo" do not clash
     * with each other, two German "hallo" do.
     *
     * @return array<string,mixed>
     */
    protected function translatableRules(Field $field, Request $request, string $context, ?Model $record): array
    {
        $name = $field->getName();
        $languages = $this->contentLanguages();
        $rules = [$name => ['nullable', 'array']];

        foreach ($languages->codes() as $code) {
            $key = "{$name}.{$code}";

            $fieldRules = $field->getValidationRules(
                new FieldContext($context, $record, $request->input($key), $request->has($key))
            );

            if ($code !== $languages->default()) {
                $fieldRules = array_values(array_filter($fieldRules, static fn ($rule): bool => $rule !== 'required'));

                if (! in_array('nullable', $fieldRules, true)) {
                    array_unshift($fieldRules, 'nullable');
                }
            }

            $rules[$key] = array_map(function ($rule) use ($name, $code, $record) {
                if (! is_string($rule) || ! str_starts_with($rule, 'unique:')) {
                    return $rule;
                }

                $parameters = explode(',', substr($rule, strlen('unique:')));
                $column = ($parameters[1] ?? '') !== '' && strtoupper($parameters[1]) !== 'NULL' ? $parameters[1] : $name;

                return $this->uniqueRule($parameters[0], "{$column}->{$code}", $record);
            }, $fieldRules);
        }

        return $rules;
    }

    /**
     * A unique rule that exempts the record being edited and, for a module
     * whose records belong to sites, looks only at the current site: two
     * sites may both have /about.
     */
    protected function uniqueRule(string $table, string $column, ?Model $record): Unique
    {
        $rule = Rule::unique($table, $column);

        if ($record !== null && $record->exists) {
            $rule->ignore($record->getKey(), $record->getKeyName());
        }

        if ($this->isPerSite() && $table === (new $this->model)->getTable()) {
            $rule->where('site_id', app(Sites::class)->current()->id);
        }

        return $rule;
    }

    protected function isPerSite(): bool
    {
        return in_array(BelongsToSite::class, class_uses_recursive($this->model), true);
    }

    /**
     * A row of the list: translatable columns in the default language, the
     * values of columns with their own value().
     *
     * @param  array<int,string>  $related
     * @param  array<int,Column>  $computed
     * @return array<string,mixed>
     */
    protected function listRow(Model $item, array $related = [], array $computed = []): array
    {
        $row = $item->toArray();
        $default = $this->contentLanguages()->default();

        foreach ($this->translatableAttributes() as $name) {
            // A package may add names that are not the model's own translations.
            if (array_key_exists($name, $row) && method_exists($item, 'isTranslatableAttribute') && method_exists($item, 'getTranslation') && $item->isTranslatableAttribute($name)) {
                $row[$name] = $item->getTranslation($name, $default);
            }
        }

        foreach ($related as $name) {
            $record = data_get($item, Str::beforeLast($name, '.'));
            $attribute = Str::afterLast($name, '.');

            $row[$name] = $record instanceof Model && method_exists($record, 'isTranslatableAttribute') && method_exists($record, 'getTranslation') && $record->isTranslatableAttribute($attribute)
                ? $record->getTranslation($attribute, $default)
                : data_get($record, $attribute);
        }

        foreach ($computed as $column) {
            $row[$column->getName()] = $column->valueOf($item);
        }

        return Hook::apply('atrium.record.present', $row, $item, 'list');
    }

    /**
     * Names of the columns that show a related record (`brand.name`). They
     * are neither sorted nor searched by.
     *
     * @param  array<int,mixed>  $columns
     * @return array<int,string>
     */
    protected function relatedColumns(array $columns): array
    {
        // A column with its own value() is no relation, dot or not.
        $columns = array_filter($columns, static fn ($column): bool => ! ($column instanceof Column && $column->isComputed()));
        $names = array_map(static fn ($column): string => (string) ($column->toArray()['name'] ?? ''), $columns);

        return array_values(array_filter($names, static fn (string $name): bool => str_contains($name, '.')));
    }

    /**
     * Serialize columns for frontend.
     */
    protected function serializeColumns(array $columns): array
    {
        return collect($columns)
            ->map(fn ($column) => $column->toArray())
            ->toArray();
    }
}
