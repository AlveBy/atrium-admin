<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Resources\ResourceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The search of a module's relation field ("Bought with it" of a product):
 * a page of the linked module's records by a few words, for forms whose
 * field has too many records to list them all.
 *
 * The route needs the right to see the module (AuthorizeAdminRoute, as its
 * form); the records need the right to see their own module, when it is one.
 * The site and the field's query() narrow them as they narrow the form.
 */
final class RelationOptionsController
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Permissions $permissions,
    ) {}

    public function search(Request $request, string $field): JsonResponse
    {
        // By name: the route passes its values in order, and the module is a default after the field.
        $relation = $this->field((string) $request->route('module'), $field);
        $target = $this->resources->keyOf((string) $relation->getModelClass());
        /** @var AdminUser $user behind the admin's auth middleware */
        $user = $request->user(config('atrium.auth.guard', 'admin'));

        abort_if($target !== null && ! $this->permissions->abilities($user, $target)['view'], 403, __('atrium::permissions.forbidden'));

        $text = $request->query('q');

        return response()->json($relation->search(is_string($text) ? $text : '', $request->integer('page')));
    }

    /**
     * The module's relation field by name, with a model to search in.
     */
    private function field(string $module, string $name): BelongsToField
    {
        foreach (AdminController::fieldsOf((string) $this->resources->model($module)) as $field) {
            if ($field instanceof BelongsToField && $field->getName() === $name && $field->getModelClass() !== null) {
                return $field;
            }
        }

        abort(404);
    }
}
