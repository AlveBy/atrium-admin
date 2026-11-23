<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Atrium\Admin\Support\Like;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

class BelongsToField extends Field
{
    protected ?string $modelClass = null;

    protected string $displayColumn = 'name';

    protected string $valueColumn = 'id';

    protected bool $searchable = true;

    protected bool $clearable = true;

    protected ?string $searchRoute = null;

    protected ?Closure $queryCallback = null;

    /** Up to this many records the form gets them all; past it, a search box. */
    protected int $searchThreshold = 50;

    /** Where the form searches the records: the module's options route. */
    protected ?string $searchUrl = null;

    /** The record the form edits, for the names of the records already chosen. */
    protected ?Model $record = null;

    /** Records per answer of the search. */
    public const PER_PAGE = 20;

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
     * How many records the form may get in full before it searches instead.
     */
    public function searchThreshold(int $records): static
    {
        $this->searchThreshold = $records;

        return $this;
    }

    /**
     * Where the form searches the records. The admin's form sets it to the
     * module's options route; a field without it keeps the whole list.
     */
    public function searchUrl(?string $url): static
    {
        $this->searchUrl = $url;

        return $this;
    }

    /**
     * The record being edited: the records it already links to come with
     * the form by name when the rest are searched.
     */
    public function record(?Model $record): static
    {
        $this->record = $record;

        return $this;
    }

    /**
     * The records the field offers: the model's, narrowed by query().
     *
     * @return Builder<Model>
     */
    protected function relationQuery(): Builder
    {
        /** @var class-string<Model> $model */
        /** @var class-string<Model> $model */
        $model = $this->modelClass;
        $query = $model::query();

        if ($this->queryCallback) {
            $query = ($this->queryCallback)($query);
        }

        return $query;
    }

    /**
     * Get options from model
     */
    public function getOptions(): array
    {
        if (! $this->modelClass) {
            return [];
        }

        return $this->relationQuery()->get()
            ->map(fn (Model $item): array => $this->option($item))
            ->toArray();
    }

    /**
     * A record as the form shows it.
     *
     * @return array{value:mixed,label:mixed}
     */
    protected function option(Model $record): array
    {
        return [
            'value' => $record->{$this->valueColumn},
            'label' => $record->{$this->displayColumn},
        ];
    }

    /**
     * One page of the records whose name contains the text, or whose id it
     * is, the way the module's own list shows them (its scopeAdminList()).
     *
     * @return array{data:array<int,array{value:mixed,label:mixed}>,more:bool}
     */
    public function search(string $text, int $page): array
    {
        /** @var class-string<Model> $model */
        /** @var class-string<Model> $model */
        $model = $this->modelClass;
        $instance = new $model;
        $query = $this->relationQuery();

        if (method_exists($instance, 'scopeAdminList')) {
            $query = $instance->scopeAdminList($query);
        }

        $text = trim($text);

        if ($text !== '') {
            $column = $this->displayColumn;

            $query->where(function (Builder $where) use ($instance, $column, $text): void {
                if (ctype_digit($text)) {
                    $where->orWhere($instance->getQualifiedKeyName(), (int) $text);
                }

                if (method_exists($instance, 'isTranslatableAttribute') && $instance->isTranslatableAttribute($column)) {
                    $where->orWhere(fn (Builder $q) => $q->whereTranslationLike($column, $text));
                } else {
                    Like::contains($where, $instance->qualifyColumn($column), $text, 'or');
                }
            });
        }

        // A page that is not a number from 1 up is the first one.
        $records = $query->orderBy($instance->getQualifiedKeyName())->simplePaginate(self::PER_PAGE, ['*'], 'page', $page);

        return [
            'data' => $records->getCollection()->map(fn (Model $item): array => $this->option($item))->all(),
            'more' => $records->hasMorePages(),
        ];
    }

    /**
     * Where the form searches, or null when it gets every record: a search
     * route set by hand always; the options route once there are more
     * records than the threshold.
     */
    public function getSearchUrl(): ?string
    {
        if (! $this->searchable || ! $this->modelClass) {
            return null;
        }

        if ($this->searchRoute !== null) {
            return Route::has($this->searchRoute) ? route($this->searchRoute) : $this->searchRoute;
        }

        if ($this->searchUrl !== null && $this->relationQuery()->count() > $this->searchThreshold) {
            return $this->searchUrl;
        }

        return null;
    }

    /**
     * The values of the edited record.
     *
     * @return array<int,mixed>
     */
    protected function currentValues(): array
    {
        return [$this->record?->getAttribute($this->name)];
    }

    /**
     * The chosen records by name, in the order they were chosen. The
     * field's query() does not narrow them: a link made before still shows.
     *
     * @return array<int,array{value:mixed,label:mixed}>
     */
    protected function selectedOptions(): array
    {
        $values = array_filter($this->currentValues(), static fn (mixed $value): bool => is_scalar($value) && $value !== '');

        if ($values === []) {
            return [];
        }

        /** @var class-string<Model> $model */
        /** @var class-string<Model> $model */
        $model = $this->modelClass;
        $records = $model::query()->whereIn($this->valueColumn, $values)->get()
            ->keyBy(fn (Model $item): string => (string) $item->{$this->valueColumn});

        $selected = [];

        foreach ($values as $value) {
            if ($records->has((string) $value)) {
                $selected[] = $this->option($records->get((string) $value));
            }
        }

        return $selected;
    }

    protected function getFieldProps(): array
    {
        $searchUrl = $this->getSearchUrl();

        return [
            'modelClass' => $this->modelClass,
            'displayColumn' => $this->displayColumn,
            'valueColumn' => $this->valueColumn,
            'searchable' => $this->searchable,
            'clearable' => $this->clearable,
            'searchRoute' => $this->searchRoute,
            'searchUrl' => $searchUrl,
            // A searched field gets only the records already chosen; the
            // rest come from the search URL page by page.
            'options' => $searchUrl === null ? $this->getOptions() : [],
            'selected' => $searchUrl === null ? [] : $this->selectedOptions(),
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
