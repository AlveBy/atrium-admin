<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\ActionFailed;
use Atrium\Admin\Modules\Catalogue;
use Atrium\Admin\Modules\HasActions;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Modules\ModuleSettings;
use Atrium\Admin\Modules\Settings;
use Atrium\Admin\Support\Sites;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Modules" section: the installed module packages and their settings.
 * A module's settings need "{key}.settings" (a superadmin has it). A
 * superadmin also switches modules off and on, and sees the modules
 * required but not installed and the catalogue on Packagist.
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

        $manage = $user->isSuperadmin();
        $listed = $manage ? [...$this->modules->installed(), ...$this->modules->disabled()] : $this->modules->installed();

        foreach ($listed as $module) {
            $enabled = $this->modules->isInstalled($module->name);
            $settings = $enabled && $module->settings !== null && $this->permissions->canSetUp($user, $module->key());

            if (! $settings && ! $manage) {
                continue;
            }

            $rows[] = [
                'name' => $module->name,
                'key' => $module->key(),
                'label' => $module->label(),
                'description' => $module->description,
                'settings' => $settings,
                'enabled' => $enabled,
                'parent' => $this->modules->parentOf($module)?->name,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        // Submodules right under their parent.
        $listed = array_column($rows, 'name');
        $ordered = [];

        foreach ($rows as $row) {
            if ($row['parent'] === null || ! in_array($row['parent'], $listed, true)) {
                $ordered[] = $row;
                array_push($ordered, ...array_filter($rows, static fn (array $child): bool => $child['parent'] === $row['name']));
            }
        }

        $rows = $ordered;

        $required = [];

        foreach ($manage ? $this->modules->all() : [] as $module) {
            if (! $this->modules->isInstalled($module->name) && ! $this->modules->isDisabled($module->name)) {
                $required[] = ['name' => $module->name, 'label' => $module->label(), 'description' => $module->description];
            }
        }

        return Inertia::render('Admin/Modules/Index', [
            'modules' => $rows,
            'required' => $required,
            'canManage' => $manage,
            'catalogue' => $manage ? Inertia::defer(fn (): array => $this->catalogue()) : null,
            'title' => __('atrium::modules.title'),
        ]);
    }

    /**
     * Switch an installed module on again. Its submodules come back with it;
     * a submodule needs its parent switched on.
     */
    public function enable(Request $request, string $module): RedirectResponse
    {
        $found = $this->manageable($request, $module, disabled: true);
        $parent = $this->modules->parentOf($found);

        if ($parent !== null && ! $this->modules->isInstalled($parent->name)) {
            return back()->with('error', __('atrium::modules.parent_off', ['parent' => $parent->label()]));
        }

        foreach ([$found, ...$this->modules->childrenOf($found)] as $each) {
            if (! $this->modules->isDisabled($each->name)) {
                continue;
            }

            if (! $this->modules->enable($each)) {
                return back()->with('error', __('atrium::modules.config_locked'));
            }

            // Migrations it shipped while it was off.
            if ($each->migrations !== []) {
                Artisan::call('migrate', ['--path' => $each->migrations, '--realpath' => true, '--force' => true]);
            }

            Hook::fire('atrium.module.enabled', $each);
        }

        $this->refreshCaches();

        return back()->with('success', __('atrium::modules.enabled', ['module' => $found->label()]));
    }

    /**
     * Switch an installed module off, its submodules with it. Its data and
     * settings stay.
     */
    public function disable(Request $request, string $module): RedirectResponse
    {
        $found = $this->manageable($request, $module, disabled: false);

        foreach ([...$this->modules->childrenOf($found), $found] as $each) {
            if (! $this->modules->isInstalled($each->name)) {
                continue;
            }

            if (! $this->modules->disable($each)) {
                return back()->with('error', __('atrium::modules.config_locked'));
            }

            Hook::fire('atrium.module.disabled', $each);
        }

        $this->refreshCaches();

        return back()->with('success', __('atrium::modules.disabled', ['module' => $found->label()]));
    }

    public function settings(Request $request, string $module): Response
    {
        [$found, $declaration] = $this->resolve($request, $module);
        $siteId = $this->settings->siteId($declaration);

        $secrets = $declaration->secrets();
        $masks = $this->masks($declaration, $found->key(), $siteId);

        return Inertia::render('Admin/Modules/Settings', [
            'module' => ['key' => $found->key(), 'name' => $found->name, 'label' => $found->label()],
            'description' => $declaration->description(),
            'fields' => array_map(static function (Field $field) use ($masks): array {
                $props = $field->toArray();

                return isset($masks[$field->getName()]) ? [...$props, 'placeholder' => $masks[$field->getName()]] : $props;
            }, $declaration->fields()),
            'actions' => $declaration instanceof HasActions ? $declaration->actions() : [],
            // A secret never goes back to the screen: its mask is the placeholder.
            'values' => (object) array_diff_key($this->settings->stored($found->key(), $siteId), array_flip($secrets)),
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
            $rules[$name] = $field->getValidationRules(FieldContext::edit(null, Arr::get($request->all(), $name), $request->has($name)));
            $attributes[$name] = $field->getLabel();
        }

        $request->validate($rules, [], $attributes);

        $values = [];

        foreach ($fields as $field) {
            $name = $field->getName();

            // An empty secret field keeps the saved secret.
            if (in_array($name, $declaration->secrets(), true) && trim((string) $request->input($name)) === '') {
                continue;
            }

            if ($request->has($name)) {
                $values[$name] = $field instanceof CheckboxField ? $field->transformValue($request->input($name)) : $request->input($name);
            }
        }

        $this->settings->save($found->key(), $values);

        return redirect()->route('admin.modules.settings', $found->key())->with('success', __('atrium::modules.saved'));
    }

    /**
     * A button of the settings screen: what it did, or why it could not.
     */
    public function action(Request $request, string $module, string $action): RedirectResponse
    {
        [, $declaration] = $this->resolve($request, $module);

        abort_unless($declaration instanceof HasActions && in_array($action, array_column($declaration->actions(), 'name'), true), 404);

        try {
            return back()->with('success', $declaration->run($action));
        } catch (ActionFailed $failed) {
            return back()->with('error', $failed->getMessage());
        }
    }

    /**
     * What the screen shows for each saved secret, by field name.
     *
     * @return array<string,string>
     */
    private function masks(ModuleSettings $declaration, string $key, int $siteId): array
    {
        $values = $this->settings->all($key, $siteId);
        $masks = [];

        foreach ($declaration->secrets() as $name) {
            if (is_string($values[$name] ?? null) && $values[$name] !== '') {
                $masks[$name] = $declaration->mask($name, $values[$name]);
            }
        }

        return $masks;
    }

    /**
     * The modules on Packagist, each with what it is here: installed,
     * switched off, required (in composer.json, not installed) or available.
     *
     * @return array{packages:array<int,array<string,mixed>>|null}
     */
    private function catalogue(): array
    {
        $packages = app(Catalogue::class)->packages();

        foreach ($packages ?? [] as $i => $package) {
            $packages[$i]['status'] = match (true) {
                $this->modules->isInstalled($package['name']) => 'installed',
                $this->modules->isDisabled($package['name']) => 'disabled',
                $this->modules->find($package['name']) !== null => 'required',
                default => 'available',
            };
        }

        return ['packages' => $packages];
    }

    /**
     * A module a superadmin switches on (disabled: true) or off.
     */
    private function manageable(Request $request, string $key, bool $disabled): Module
    {
        abort_unless($this->user($request)->isSuperadmin(), 403, __('atrium::permissions.forbidden'));

        $module = $this->modules->findByKey($key);

        abort_if($module === null || ! ($disabled ? $this->modules->isDisabled($module->name) : $this->modules->isInstalled($module->name)), 404);

        return $module;
    }

    /**
     * Cached config and routes would not know about the change.
     */
    private function refreshCaches(): void
    {
        if (app()->configurationIsCached()) {
            Artisan::call('config:cache');
        }

        if (app()->routesAreCached()) {
            Artisan::call('route:cache');
        }
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
        // Only an AdminUser gets onto this guard: the login form, the e-mail
        // code and API tokens each refuse any other model.
        /** @var AdminUser $user */
        $user = $request->user(config('atrium.auth.guard', 'admin'));

        return $user;
    }
}
