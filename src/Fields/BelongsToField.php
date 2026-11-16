<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Closure;

class BelongsToField extends Field
{
    protected ?string $modelClass = null;

    protected string $displayColumn = 'name';

    protected string $valueColumn = 'id';

    protected bool $searchable = true;

    protected bool $clearable = true;

    protected ?string $searchRoute = null;

    protected ?Closure $queryCallback = null;

    public function getType(): string
    {
        return 'belongsTo';
    }

    /**
     * Set related model class
     */
    public function model(string $modelClass): static
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    public function getModelClass(): ?string
    {
        return $this->modelClass;
    }

    public function getDisplayColumn(): string
    {
        return $this->displayColumn;
    }

    public function getValueColumn(): string
    {
        return $this->valueColumn;
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
     * Set search route
     */
    public function searchRoute(string $route): static
    {
        $this->searchRoute = $route;

        return $this;
    }

    /**
     * Set query callback
     */
    public function query(callable $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    /**
     * Get options from model
     */
    public function getOptions(): array
    {
        if (! $this->modelClass) {
            return [];
        }

        $query = $this->modelClass::query();

        if ($this->queryCallback) {
            $query = ($this->queryCallback)($query);
        }

        return $query->get()
            ->map(fn ($item) => [
                'value' => $item->{$this->valueColumn},
                'label' => $item->{$this->displayColumn},
            ])
            ->toArray();
    }

    protected function getFieldProps(): array
    {
        return [
            'modelClass' => $this->modelClass,
            'displayColumn' => $this->displayColumn,
            'valueColumn' => $this->valueColumn,
            'searchable' => $this->searchable,
            'clearable' => $this->clearable,
            'searchRoute' => $this->searchRoute,
            // Records are searched only through a search route; without one
            // the form gets the whole list to choose from.
            'options' => $this->searchable && $this->searchRoute !== null ? [] : $this->getOptions(),
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);

        if ($this->modelClass) {
            $table = (new $this->modelClass)->getTable();
            $rules[] = "exists:{$table},{$this->valueColumn}";
        }

        return $rules;
    }
}
