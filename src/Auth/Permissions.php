<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Auth;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Modules;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * What an administrator may do in the panel.
 *
 * A permission is "{module}.{action}": `articles.view`, `articles.delete`.
 * Every module of the panel gets four actions — the panel's own sites,
 * languages and administrators included — plus "import" and "export" where
 * the module has them (Atrium\Admin\Transfer), and the "API tokens" section
 * one, `api-tokens.use`. Roles hold lists of these; a superadmin needs none.
 *
 * The modules are not listed anywhere by hand: they are the same ones the
 * menu and the API are built from, so a new module can be granted at once.
 *
 * Roles themselves are managed by superadmins only: whoever can edit roles
 * can give themselves anything. A module can keep its own screens for
 * superadmins too, by route-name prefix in `atrium.superadmin_routes`.
 */
final class Permissions
{
    /** Reading the list and opening a record (read-only without "update"). */
    public const VIEW = 'view';

    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    public const ACTIONS = [self::VIEW, self::CREATE, self::UPDATE, self::DELETE];

    /** Bringing records in from a file; a module that takes part in it has the action. */
    public const IMPORT = 'import';

    /** Taking the list out as a file; the same. */
    public const EXPORT = 'export';

    public const API_TOKENS = 'api-tokens.use';

    /** Opening and saving a module's settings: "{module key}.settings". */
    public const SETTINGS = 'settings';

    /** Route markers that are not a single permission. */
    private const SUPERADMIN = '*superadmin';

    private const ANY_WRITE = '*write';

    private const ANY_SETTINGS = '*settings';

    private const NOBODY = '*nobody';

    /**
     * What a demo's visitor may not change: their own name, e-mail and
     * password, and the settings of any module (the two-factor sign-in among
     * them: a setting there could lock the next visitor out).
     */
    private const DEMO_LOCKED = ['admin.profile.update', 'admin.profile.password', 'admin.modules.settings.update'];

    /** Admin route action => permission action. */
    private const ROUTE_ACTIONS = [
        'index' => self::VIEW,
        'edit' => self::VIEW,
        'create' => self::CREATE,
        'store' => self::CREATE,
        'update' => self::UPDATE,
        'destroy' => self::DELETE,
        // A button checks its own permission on top.
        'action' => self::VIEW,
        'bulk' => self::VIEW,
        // The records a relation field of the form searches in: as the form.
        'options' => self::VIEW,
        // Who changed a record and what: anyone who may open it.
        'history' => self::VIEW,
        // Putting an old version back is changing the record.
        'revert' => self::UPDATE,
    ];

    public function __construct(
        private readonly ApiResources $resources,
        private readonly Modules $modules,
    ) {}

    /**
     * What a role can be given, in the order the role form shows it.
     *
     * A module package with settings adds "settings" to the row of the
     * section named like its key, or a row of its own; the permissions its
     * manifest declares join their section's row the same way, with their
     * labels in `labels`.
     *
     * @return array<int,array{key:string,label:string,actions:array<int,string>,labels?:array<string,string>}>
     */
    public function sections(): array
    {
        $sections = [];

        foreach (array_keys($this->resources->modules()) as $key) {
            $sections[$key] = ['key' => $key, 'label' => $this->resources->label($key), 'actions' => [...self::ACTIONS, ...$this->transferActions($key)]];
        }

        foreach ($this->modules->installed() as $module) {
            $key = $module->key();

            if ($module->settings !== null) {
                $sections[$key] ??= ['key' => $key, 'label' => $module->label(), 'actions' => []];
                $sections[$key]['actions'][] = self::SETTINGS;
            }

            foreach ($module->declaredPermissions as $name => $permission) {
                [$section, $action] = explode('.', $name);
                $sections[$section] ??= ['key' => $section, 'label' => $section === $key ? $module->label() : Str::headline($section), 'actions' => []];

                if (! in_array($action, $sections[$section]['actions'], true)) {
                    $sections[$section]['actions'][] = $action;
                }

                if ($permission['label'] !== '') {
                    $line = __($permission['label']);
                    $sections[$section]['labels'][$action] = is_string($line) ? $line : $permission['label'];
                }
            }
        }

        usort($sections, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        $sections[] = ['key' => 'api-tokens', 'label' => __('atrium::menu.api_tokens'), 'actions' => ['use']];

        return $sections;
    }

    /**
     * Whether the module has an import or an export: its routes are there.
     *
     * @return array<int,string>
     */
    private function transferActions(string $module): array
    {
        return array_filter(
            [self::IMPORT, self::EXPORT],
            static fn (string $action): bool => Route::has("admin.{$module}.{$action}"),
        );
    }

    /**
     * Every permission that exists right now.
     *
     * @return array<int,string>
     */
    public function all(): array
    {
        $all = [];

        foreach ($this->sections() as $section) {
            foreach ($section['actions'] as $action) {
                $all[] = $section['key'].'.'.$action;
            }
        }

        return $all;
    }

    /**
     * Whether the administrator may open this admin route. Routes that belong
     * to no module (dashboard, profile, sign-out) are open to everyone.
     */
    public function allowsRoute(AdminUser $user, ?string $routeName): bool
    {
        return match ($permission = $this->routePermission($routeName)) {
            null => true,
            self::NOBODY => false,
            self::SUPERADMIN => $user->isSuperadmin(),
            self::ANY_WRITE => $this->canWriteAnything($user),
            self::ANY_SETTINGS => $this->canSetUpAnything($user),
            default => $user->hasAdminPermission($permission),
        };
    }

    /**
     * The buttons a module page may show.
     *
     * @return array{view:bool,create:bool,update:bool,delete:bool}
     */
    public function abilities(AdminUser $user, string $module): array
    {
        return [
            'view' => $user->hasAdminPermission("{$module}.view"),
            'create' => $user->hasAdminPermission("{$module}.create"),
            'update' => $user->hasAdminPermission("{$module}.update"),
            'delete' => $user->hasAdminPermission("{$module}.delete"),
        ];
    }

    /**
     * Whether the administrator may open and save a module's settings.
     */
    public function canSetUp(AdminUser $user, string $moduleKey): bool
    {
        return $user->hasAdminPermission($moduleKey.'.'.self::SETTINGS);
    }

    /**
     * Whether the administrator may set up any module: the "Modules" entry.
     */
    public function canSetUpAnything(AdminUser $user): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        foreach ($user->adminPermissions() as $permission) {
            if (str_ends_with($permission, '.'.self::SETTINGS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Uploading files is part of creating and editing records, in any module,
     * and of a module's settings.
     */
    public function canWriteAnything(AdminUser $user): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        foreach ($user->adminPermissions() as $permission) {
            if (str_ends_with($permission, '.'.self::CREATE) || str_ends_with($permission, '.'.self::UPDATE) || str_ends_with($permission, '.'.self::SETTINGS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The abilities an API token issued by this administrator may carry: never
     * more than the administrator has. "read" needs "view"; "write" needs
     * creating, editing or deleting.
     *
     * @return array<string,array{read:bool,write:bool}> module key => modes
     */
    public function tokenModes(AdminUser $user): array
    {
        $modes = [];

        foreach (array_keys($this->resources->all()) as $key) {
            $can = $this->abilities($user, $key);

            if ($can['view']) {
                $modes[$key] = ['read' => true, 'write' => $can['create'] || $can['update'] || $can['delete']];
            }
        }

        return $modes;
    }

    /**
     * The permission an admin route needs, or null when it needs none.
     */
    private function routePermission(?string $routeName): ?string
    {
        if ($routeName === null || ! str_starts_with($routeName, 'admin.')) {
            return null;
        }

        if (config('atrium.demo.enabled') && in_array($routeName, self::DEMO_LOCKED, true)) {
            return self::NOBODY;
        }

        if (str_starts_with($routeName, 'admin.roles.') || str_starts_with($routeName, 'admin.cache.')) {
            return self::SUPERADMIN;
        }

        // Screens a module keeps for superadmins (backups: a restore replaces everything).
        foreach ((array) config('atrium.superadmin_routes', []) as $prefix) {
            if (is_string($prefix) && $prefix !== '' && str_starts_with($routeName, $prefix)) {
                return self::SUPERADMIN;
            }
        }

        if (str_starts_with($routeName, 'admin.api-tokens.')) {
            return self::API_TOKENS;
        }

        if ($routeName === 'admin.upload' || str_starts_with($routeName, 'admin.filemanager.')) {
            return self::ANY_WRITE;
        }

        // The settings screens check the module's own permission.
        if (str_starts_with($routeName, 'admin.modules.')) {
            return self::ANY_SETTINGS;
        }

        // The import's screens and steps ("admin.news.import.show") need "import" as a whole.
        if (preg_match('/^admin\.([a-z0-9_]+)\.(import|export)(\.[a-z]+)?$/', $routeName, $match)
            && isset($this->resources->modules()[$match[1]])) {
            return $match[1].'.'.$match[2];
        }

        if (preg_match('/^admin\.([a-z0-9_]+)\.([a-z]+)$/', $routeName, $match)
            && isset(self::ROUTE_ACTIONS[$match[2]])
            && isset($this->resources->modules()[$match[1]])) {
            return $match[1].'.'.self::ROUTE_ACTIONS[$match[2]];
        }

        return null;
    }
}
