<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Modules\ModuleSettings;
use Atrium\Admin\Modules\Settings;
use Atrium\Admin\Support\Sites;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Modules" section: the installed module packages and their settings.
 * A module's settings need "{key}.settings" (a superadmin has it).
 */
class ModuleController extends Controller
{
    public function __construct(
        private readonly Modules $modules,
        private readonly Settings $settings,
        private readonly Permissions $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $rows = [];

        foreach ($this->modules->installed() as $module) {
            $settings = $module->settings !== null && $this->permissions->canSetUp($user, $module->key());

            if (! $settings && ! $user->isSuperadmin()) {
                continue;
            }

            $rows[] = [
                'name' => $module->name,
                'key' => $module->key(),
                'label' => $module->label(),
                'description' => $module->description,
                'settings' => $settings,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return Inertia::render('Admin/Modules/Index', [
            'modules' => $rows,
            'title' => __('atrium::modules.title'),
        ]);
    }

    public function settings(Request $request, string $module): Response
    {
        [$found, $declaration] = $this->resolve($request, $module);
        $siteId = $this->settings->siteId($declaration);

        return Inertia::render('Admin/Modules/Settings', [
            'module' => ['key' => $found->key(), 'name' => $found->name, 'label' => $found->label()],
            'description' => $declaration->description(),
            'fields' => array_map(static fn (Field $field): array => $field->toArray(), $declaration->fields()),
            'values' => (object) $this->settings->stored($found->key(), $siteId),
            'site' => $declaration->perSite ? app(Sites::class)->find($siteId)?->only(['id', 'name']) : null,
            'title' => __('atrium::modules.settings_title', ['module' => $found->label()]),
        ]);
    }

    public function update(Request $request, string $module): RedirectResponse
    {
        [$found, $declaration] = $this->resolve($request, $module);
        $fields = $declaration->fields();
        $rules = $attributes = [];

        foreach ($fields as $field) {
            $name = $field->getName();
            $rules[$name] = $field->getValidationRules(FieldContext::edit(null, $request->file($name) ?? $request->input($name), $request->has($name)));
            $attributes[$name] = $field->getLabel();
        }

        $request->validate(array_filter($rules), [], array_filter($attributes));

        $values = [];

        foreach ($fields as $field) {
            $name = $field->getName();

            if ($request->has($name)) {
                $values[$name] = $field instanceof CheckboxField ? $field->transformValue($request->input($name)) : $request->input($name);
            }
        }

        $this->settings->save($found->key(), $values);

        return redirect()->route('admin.modules.settings', $found->key())->with('success', __('atrium::modules.saved'));
    }

    /**
     * @return array{0:Module,1:ModuleSettings}
     */
    private function resolve(Request $request, string $key): array
    {
        $module = $this->modules->findByKey($key);
        $declaration = $this->settings->declaration($key);

        abort_if($module === null || $declaration === null, 404);
        abort_unless($this->permissions->canSetUp($this->user($request), $key), 403, __('atrium::permissions.forbidden'));

        return [$module, $declaration];
    }

    private function user(Request $request): AdminUser
    {
        $user = $request->user(config('atrium.auth.guard', 'admin'));
        abort_unless($user instanceof AdminUser, 403);

        return $user;
    }
}
