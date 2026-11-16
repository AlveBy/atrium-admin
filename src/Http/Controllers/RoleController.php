<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Models\AdminRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Roles" section: named sets of permissions for administrators.
 * Superadmins only — the route check (admin.can) sees to that.
 */
class RoleController extends Controller
{
    public function __construct(private readonly Permissions $permissions) {}

    public function index(): Response
    {
        $roles = AdminRole::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (AdminRole $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions_count' => count(array_intersect((array) $role->permissions, $this->permissions->all())),
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'title' => __('atrium::menu.roles'),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        AdminRole::query()->create($this->validated($request, null));

        return redirect()->route('admin.roles.index')->with('success', __('atrium::permissions.saved'));
    }

    public function edit(int $role): Response
    {
        return $this->form(AdminRole::query()->findOrFail($role));
    }

    public function update(Request $request, int $role): RedirectResponse
    {
        $model = AdminRole::query()->findOrFail($role);
        $model->update($this->validated($request, $model));

        return redirect()->route('admin.roles.index')->with('success', __('atrium::permissions.saved'));
    }

    public function destroy(int $role): RedirectResponse
    {
        AdminRole::query()->findOrFail($role)->delete();

        return redirect()->route('admin.roles.index')->with('success', __('atrium::permissions.deleted'));
    }

    private function form(?AdminRole $role): Response
    {
        return Inertia::render('Admin/Roles/Edit', [
            'role' => $role === null ? null : [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => array_values(array_intersect((array) $role->permissions, $this->permissions->all())),
            ],
            'sections' => $this->permissions->sections(),
            'title' => $role === null
                ? __('atrium::permissions.create_title')
                : __('atrium::permissions.edit_title', ['name' => $role->name]),
        ]);
    }

    /**
     * @return array{name:string,permissions:array<int,string>}
     */
    private function validated(Request $request, ?AdminRole $role): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('admin_roles', 'name')->ignore($role?->id)],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in($this->permissions->all())],
        ], [], [
            'name' => __('atrium::permissions.name'),
        ]);

        return [
            'name' => $validated['name'],
            'permissions' => array_values(array_unique($validated['permissions'])),
        ];
    }
}
