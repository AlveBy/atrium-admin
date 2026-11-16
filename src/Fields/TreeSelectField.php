<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class TreeSelectField extends Field
{
    protected ?string $modelClass = null;

    protected string $displayColumn = 'name';

    protected string $valueColumn = 'id';

    protected string $parentColumn = 'parent_id';

    protected bool $searchable = true;

    protected bool $clearable = true;

    protected bool $multiple = false;

    protected bool $expandAll = false;

    protected ?string $rootLabel = null;

    protected bool $rootLabelSet = false;

    public function getType(): string
    {
        return 'treeSelect';
    }

    /**
     * Set related model class
     */
    public function model(string $modelClass): static
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /**
     * Set display column
     */
    public function displayColumn(string $column): static
    {
        $this->displayColumn = $column;

        return $this;
    }

    /**
     * Set value column
     */
    public function valueColumn(string $column): static
    {
        $this->valueColumn = $column;

        return $this;
    }

    /**
     * Set parent column
     */
    public function parentColumn(string $column): static
    {
        $this->parentColumn = $column;

        return $this;
    }

    /**
     * Enable/disable search
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Enable/disable clear button
     */
    public function clearable(bool $clearable = true): static
    {
        $this->clearable = $clearable;

        return $this;
    }

    /**
     * Enable multiple selection
     */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * Подпись строки «ничего не выбрано» в самом верху дерева.
     *
     * Без неё список родителей — это только уже заведённые разделы, и
     * «создать раздел верхнего уровня» превращается в догадку: заказчик,
     * добавляя Японию, увидел в «Родительском разделе» одни страны и решил,
     * что новую страну можно завести только внутри старой. Пустое значение
     * работало и раньше — но выбрать его было нечем.
     *
     * null — строки нет (так у привязки элемента к разделу: «без раздела»
     * там не нормальный выбор, а потерянная страница).
     */
    public function rootLabel(?string $label): static
    {
        $this->rootLabel = $label;
        $this->rootLabelSet = true;

        return $this;
    }

    /**
     * Дерево, вложенное само в себя (`parent_id` → та же модель), — ровно тот
     * случай, где «ничего не выбрано» значит «верхний уровень».
     */
    protected function defaultRootLabel(): ?string
    {
        if ($this->multiple || $this->name !== $this->parentColumn) {
            return null;
        }

        return 'Верхний уровень';
    }

    /**
     * Expand all nodes by default
     */
    public function expandAll(bool $expand = true): static
    {
        $this->expandAll = $expand;

        return $this;
    }

    /**
     * Get tree options from model
     */
    public function getTreeOptions(): array
    {
        if (! $this->modelClass) {
            return [];
        }

        $items = $this->modelClass::orderBy('sort')
            ->orderBy($this->displayColumn)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->{$this->valueColumn},
                'label' => $item->{$this->displayColumn},
                'parent_id' => $item->{$this->parentColumn},
            ])
            ->toArray();

        return $this->buildTree($items);
    }

    /**
     * Build tree structure from flat array
     */
    protected function buildTree(array $items, $parentId = null): array
    {
        $tree = [];

        foreach ($items as $item) {
            if ($item['parent_id'] === $parentId) {
                $children = $this->buildTree($items, $item['id']);
                $node = [
                    'value' => $item['id'],
                    'label' => $item['label'],
                ];

                if (! empty($children)) {
                    $node['children'] = $children;
                }

                $tree[] = $node;
            }
        }

        return $tree;
    }

    protected function getFieldProps(): array
    {
        return [
            'modelClass' => $this->modelClass,
            'displayColumn' => $this->displayColumn,
            'valueColumn' => $this->valueColumn,
            'parentColumn' => $this->parentColumn,
            'searchable' => $this->searchable,
            'clearable' => $this->clearable,
            'multiple' => $this->multiple,
            'expandAll' => $this->expandAll,
            'rootLabel' => $this->rootLabelSet ? $this->rootLabel : $this->defaultRootLabel(),
            'options' => $this->getTreeOptions(),
            'model' => $this->modelClass,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);

        if ($this->multiple) {
            // `array` instead of `exists`, but without clobbering an explicit
            // ->rules() the way the previous `$rules = ['array']` reset did.
            $rules[] = 'array';

            return $rules;
        }

        if ($this->modelClass) {
            $table = (new $this->modelClass)->getTable();
            $rules[] = "exists:{$table},{$this->valueColumn}";
        }

        if ($rule = $this->notItsOwnAncestorRule($context)) {
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * Раздел не может лежать внутри себя или внутри собственного потомка.
     *
     * В дереве родителей показаны все разделы, включая правимый, и `exists`
     * такой выбор пропускает. Дальше HasAdminSlug идёт от раздела к корню за
     * путём ЧПУ — и в кольце этот подъём не заканчивается никогда: сохранение
     * не падает с ошибкой, а вешает запрос.
     */
    protected function notItsOwnAncestorRule(?FieldContext $context): ?\Closure
    {
        if (! $this->modelClass || $this->multiple || $this->name !== $this->parentColumn) {
            return null;
        }

        $record = $context?->record;

        if (! $record || ! $record->exists) {
            return null;
        }

        $modelClass = $this->modelClass;
        $parentColumn = $this->parentColumn;

        return function (string $attribute, $value, \Closure $fail) use ($record, $modelClass, $parentColumn): void {
            if (! $value) {
                return;
            }

            $rows = $modelClass::query()->pluck($parentColumn, 'id');
            $cursor = (int) $value;

            for ($depth = 0; $cursor && $depth <= $rows->count(); $depth++) {
                if ($cursor === (int) $record->getKey()) {
                    $fail('Раздел нельзя положить внутрь себя или своего подраздела.');

                    return;
                }

                $cursor = (int) ($rows[$cursor] ?? 0);
            }
        };
    }
}
