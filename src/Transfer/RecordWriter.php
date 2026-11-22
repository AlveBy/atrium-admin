<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Http\Controllers\AdminController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Saves an imported row exactly as the module's form and the API save one:
 * the same validation, the same custom fields, SEO and languages, the same
 * hooks after the save. A row is a form that a file filled in. And reads a
 * record for an export the way the form would show it, and the module's list
 * as the list screen narrows it.
 *
 * A row that updates a record is partial, like the API's PATCH: only the
 * columns of the file are validated and written, the rest stays as it was.
 */
final class RecordWriter extends AdminController
{
    /**
     * @param  class-string<Model&Administrable>  $model
     */
    public function __construct(string $model)
    {
        $this->model = $model;
    }

    /**
     * The fields of the module's form, the SEO and custom ones included.
     *
     * @return array<int,Field>
     */
    public function fields(): array
    {
        return $this->allAdminFields();
    }

    /**
     * The names of the fields that hold a value per content language.
     *
     * @return array<int,string>
     */
    public function translatable(): array
    {
        return $this->translatableAttributes();
    }

    /**
     * What would stop the row from saving; nothing is written.
     *
     * @param  array<string,mixed>  $data
     * @return array<int,string>
     */
    public function check(array $data, bool $exists, ?Model $record): array
    {
        $context = $exists ? 'edit' : 'create';
        $request = Request::create('/', 'POST', $data);
        $this->normalizeTranslatableInput($request);

        return Validator::make($request->all(), $this->resolveRules($request, $context, $record), [], $this->validationAttributes($context))
            ->errors()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function write(array $data, (Model&Administrable)|null $record): Model&Administrable
    {
        return $this->saveItem(Request::create('/', 'POST', $data), $record === null ? 'create' : 'edit', $record ?? new $this->model);
    }

    /**
     * A record as the form shows it: translations per language, the SEO
     * fields, custom fields.
     *
     * @return array<string,mixed>
     */
    public function present(Model $record): array
    {
        return $this->prepareItemData($record) ?? [];
    }

    /**
     * The records of the module's list as a request narrows it: its filters,
     * search and sort, without a page.
     *
     * @return Builder<Model&Administrable>
     */
    public function list(Request $request): Builder
    {
        [$query] = $this->listQuery($request);

        return $query;
    }

    protected function resolveRules(Request $request, string $context, ?Model $record): array
    {
        $rules = parent::resolveRules($request, $context, $record);

        if ($context === 'edit') {
            $rules = array_filter($rules, static fn ($rule, $name): bool => $request->has($name), ARRAY_FILTER_USE_BOTH);
        }

        return $rules;
    }
}
