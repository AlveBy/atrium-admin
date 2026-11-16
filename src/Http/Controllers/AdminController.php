<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Support\PublicUrl;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
        return [
            'routeName' => $this->model::getAdminRouteName(),
            'createLabel' => $this->model::getAdminCreateLabel(),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = (new $this->model)->scopeAdminList($this->model::query());

        // Apply search
        if ($search = $request->get('search')) {
            // Column::$searchable and $name are protected, so reading them as
            // properties yields null (the ?? swallows the visibility error) and
            // the filter matched nothing — search silently returned every row.
            $searchable = collect($this->model::getAdministrationListColumns())
                ->map(fn ($col) => $col->toArray())
                ->filter(fn (array $col): bool => (bool) ($col['searchable'] ?? false))
                ->pluck('name')
                ->filter()
                ->values()
                ->toArray();

            if (! empty($searchable)) {
                $query->where(function ($q) use ($searchable, $search) {
                    foreach ($searchable as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }
        }

        // Apply sorting
        $sortBy = $request->get('sort', $this->model::getDefaultSortColumn());
        $sortDir = $request->get('direction', $this->model::getDefaultSortDirection());
        $query->orderBy($sortBy, $sortDir);

        $items = $query->paginate($this->model::getAdminPerPage())->withQueryString();

        return Inertia::render($this->getPagePrefix().'/Index', [
            'items' => $items,
            'columns' => $this->serializeColumns($this->model::getAdministrationListColumns()),
            'filters' => [
                'search' => $request->get('search', ''),
                'sort' => $sortBy,
                'direction' => $sortDir,
            ],
            'title' => $this->model::getAdminLabel(),
            ...$this->resourceProps(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $data = $this->preselectSection($request, $this->prepareFormData('create'));

        return Inertia::render($this->getPagePrefix().'/Create', [...$data, ...$this->resourceProps()]);
    }

    /**
     * Подставить раздел, с которого пришли.
     *
     * Публичная панель уводит на эту форму прямо с открытого раздела сайта
     * (`?section_id=12`) — раздел должен быть уже выбран, иначе заказчик,
     * нажавший «Добавить товар» на «Турах по Беларуси», ищет их заново в
     * дереве и половину раз промахивается.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function preselectSection(Request $request, array $data): array
    {
        $sectionId = (int) $request->query(self::SECTION_QUERY, 0);

        if ($sectionId <= 0 || ! $this->sectionExists($sectionId)) {
            return $data;
        }

        $preselect = static function (array $field) use ($sectionId): array {
            if (($field['name'] ?? null) === self::SECTION_QUERY) {
                $field['default'] = $sectionId;
            }

            return $field;
        };

        if (isset($data['fields']) && is_array($data['fields'])) {
            $data['fields'] = array_map($preselect, $data['fields']);
        }

        if (isset($data['fieldsByTab']) && is_array($data['fieldsByTab'])) {
            $data['fieldsByTab'] = array_map(
                static fn (array $fields): array => array_map($preselect, $fields),
                $data['fieldsByTab']
            );
        }

        return $data;
    }

    /**
     * Есть ли такой раздел на самом деле: по кривой ссылке лучше открыть форму
     * без выбранного раздела, чем с несуществующим.
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
            ->with('success', 'Запись успешно создана.');
    }

    /**
     * Validate the request and write it to $item — one path for the form and
     * for the API, so a record saved by an agent is saved exactly like one
     * saved by hand.
     *
     * ignoreCurrentRecord() inside resolveRules() handles unique-ignore on
     * edit for every attribute, not just `email`.
     */
    protected function saveItem(Request $request, string $context, Model&Administrable $item): Model&Administrable
    {
        $record = $context === 'create' ? null : $item;

        $this->runValidation($request, $this->resolveRules($request, $context, $record), $context);

        $validated = $this->submittedData($request, $context, $item);

        // Hash password if present; on edit an empty one means "keep".
        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } elseif ($context === 'edit') {
            unset($validated['password']);
        }

        $validated = $item->transformAdminData($validated);

        // BelongsToMany values live in a pivot table, not a column. They must
        // be pulled out before fill(), or the insert fails with
        // "Unknown column 'tizers' in 'field list'" when the relation name also
        // appears in $fillable.
        $relations = $this->extractRelationValues($validated);
        $seo = $this->extractSeoValues($validated);

        $item->fill($validated);
        $item->save();

        $this->syncRelations($item, $relations);
        $this->persistSeoValues($item, $seo);

        $item->afterAdminSave($validated);

        return $item;
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
            return $this->ignoreCurrentRecord($explicit, $record);
        }

        $rules = [];

        foreach ($this->contextFields($context) as $field) {
            $name = $field->getName();

            // input() does not see uploads, and ImageField/FileField need to
            // know whether this is a real file or the stored path.
            $value = $request->file($name) ?? $request->input($name);

            $rules[$name] = $field->getValidationRules(
                new FieldContext($context, $record, $value, $request->has($name))
            );
        }

        return $this->ignoreCurrentRecord($rules, $record);
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

            if ($label !== '') {
                $attributes[$field->getName()] = mb_strtolower($label);
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
        if (empty($rules)) {
            return;
        }

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
            $keys[] = $field->getName();
        }

        $data = $request->only(array_values(array_unique($keys)));

        // Нетронутая галочка приезжает пустой строкой: форма заводит каждое
        // поле как `default ?? ''`, а ConvertEmptyStringsToNull делает из
        // пустой строки null. Правило `nullable|boolean` такое пропускает, и
        // null доезжает до колонки — а `on_main_page` объявлена
        // `boolean NOT NULL default false`, и вся запись отваливается с
        // «Column 'on_main_page' cannot be null». То есть тур не сохранялся,
        // пока никто не трогал галочку «На главной».
        //
        // Снятая галочка — это false (или отсутствие битриксового кода), а не
        // «неизвестно». Чем именно её хранить, знает сама CheckboxField:
        // boolean либо строку-код из Битрикса ('YES').
        foreach ($this->contextFields($context) as $field) {
            $name = $field->getName();

            if ($field instanceof CheckboxField && array_key_exists($name, $data)) {
                $data[$name] = $field->transformValue($data[$name]);
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
        if ($record === null || ! $record->exists) {
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

                $normalized[$index] = Rule::unique($table, $column)
                    ->ignore($record->getKey(), $record->getKeyName());

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
        $belongsToManyRelations = $this->getBelongsToManyRelations();
        if (! empty($belongsToManyRelations)) {
            $item->load($belongsToManyRelations);
        }

        $data = $this->prepareFormData('edit', $item);

        return Inertia::render($this->getPagePrefix().'/Edit', [...$data, ...$this->resourceProps()]);
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
            $fieldArray = $field->toArray();

            if ($fieldArray['type'] !== 'belongsToMany') {
                continue;
            }

            $name = $fieldArray['name'];

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
            $fieldArray = $field->toArray();
            if ($fieldArray['type'] === 'belongsToMany') {
                // Convert snake_case field name to camelCase relationship method name
                $relations[] = Str::camel($fieldArray['name']);
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
            ->with('success', 'Запись успешно обновлена.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $item = $this->findItem($id);

        if (! $item->canDelete()) {
            return back()->with('error', 'Эту запись нельзя удалить.');
        }

        if (! $item->beforeAdminDelete()) {
            return back()->with('error', 'Ошибка при удалении записи.');
        }

        $item->delete();

        return redirect()
            ->route($this->model::getAdminRouteName().'.index')
            ->with('success', 'Запись успешно удалена.');
    }

    /**
     * Prepare form data with tabs support.
     */
    protected function prepareFormData(string $context, $item = null): array
    {
        $title = $item
            ? 'Редактировать: '.$item->getAdminTitle()
            : 'Создать '.$this->model::getAdminLabel();

        $adminLabel = $this->model::getAdminLabel();

        // Check if model supports tabs
        if (method_exists($this->model, 'getFieldsGroupedByTabs') && method_exists($this->model, 'usesTabs') && $this->model::usesTabs()) {
            $tabsData = $this->model::getFieldsGroupedByTabs($context);

            return [
                'tabs' => $tabsData['tabs'],
                'fieldsByTab' => $this->serializeFieldsByTab($tabsData['fields'], $context),
                'fields' => $this->serializeFields(
                    $this->model::getAdminFieldsWithTabs(),
                    $context
                ), // For backward compatibility
                'item' => $this->prepareItemData($item),
                'title' => $title,
                'adminLabel' => $adminLabel,
                'useTabs' => true,
            ];
        }

        // Fallback: old logic without tabs
        $fields = $this->allAdminFields();

        return [
            'fields' => $this->serializeFields($fields, $context),
            'item' => $this->prepareItemData($item),
            'title' => $title,
            'adminLabel' => $adminLabel,
            'useTabs' => false,
        ];
    }

    /**
     * Prepare item data with additional computed fields.
     */
    /**
     * Every field the form renders, including the SEO block.
     *
     * HasAdminSeo gives 31 models getSeoAdminFields() and an updateSeoMeta(),
     * and every model declares a `seo` tab — but nothing ever merged the two,
     * so the SEO tab rendered "Нет полей для отображения" everywhere and no
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

        return $fields;
    }

    /**
     * Form field name => seo_meta column.
     *
     * Карта берётся у самой модели (HasAdminSeo::seoFieldMap()), а не держится
     * здесь вторым списком: разъехавшиеся копии уже стоили нам восьми полей,
     * которые форма показывала, а проверка считала непросохранёнными.
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

        $data = $item->toArray();

        // Transform BelongsToMany relations to arrays of IDs
        // Laravel's toArray() returns full objects, but Vue components need simple ID arrays
        foreach ($item->getRelations() as $name => $relation) {
            if ($relation instanceof Collection) {
                $data[$name] = $relation->pluck('id')->toArray();
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

        return $data;
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
            // Превью под «Символьным кодом» должно показывать адрес сайта, а
            // не путь ЧПУ админки: у страны это /countries/china, а не /china.
            // Поле само модель не знает — карту адресов кладёт контроллер.
            ->map(fn (array $field) => PublicUrl::decorateSlugField($field, $this->model))
            ->values()
            ->toArray();
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
