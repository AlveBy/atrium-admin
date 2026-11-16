<?php

declare(strict_types=1);

namespace Atrium\Admin\Auth;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Models\AdminUser;

/**
 * What an administrator may do in the panel.
 *
 * A permission is "{module}.{action}": `articles.view`, `articles.delete`.
 * Every module of the panel gets four actions — the panel's own sites,
 * languages and administrators included — and the "API tokens" section one,
 * `api-tokens.use`. Roles hold lists of these; a superadmin needs none.
 *
 * The modules are not listed anywhere by hand: they are the same ones the
 * menu and the API are built from, so a new module can be granted at once.
 *
 * Roles themselves are managed by superadmins only: whoever can edit roles
 * can give themselves anything.
 */
final class Permissions
{
    /** Reading the list and opening a record (read-only without "update"). */
    public const VIEW = 'view';

    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    public const ACTIONS = [self::VIEW, self::CREATE, self::UPDATE, self::DELETE];

    public const API_TOKENS = 'api-tokens.use';

    /** Route markers that are not a single permission. */
    private const SUPERADMIN = '*superadmin';

    private const ANY_WRITE = '*write';

    /** Admin route action => permission action. */
    private const ROUTE_ACTIONS = [
        'index' => self::VIEW,
        'edit' => self::VIEW,
        'create' => self::CREATE,
        'store' => self::CREATE,
        'update' => self::UPDATE,
        'destroy' => self::DELETE,
    ];

    public function __construct(private readonly ApiResources $resources) {}

    /**
     * What a role can be given, in the order the role form shows it.
     *
     * @return array<int,array{key:string,label:string,actions:array<int,string>}>
     */
    public function sections(): array
    {
        $sections = [];

        foreach (array_keys($this->resources->modules()) as $key) {
            $sections[] = ['key' => $key, 'label' => $this->resources->label($key), 'actions' => self::ACTIONS];
        }

        usort($sections, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        $sections[] = ['key' => 'api-tokens', 'label' => __('atrium::menu.api_tokens'), 'actions' => ['use']];

        return $sections;
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
            self::SUPERADMIN => $user->isSuperadmin(),
            self::ANY_WRITE => $this->canWriteAnything($user),
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
     * Uploading files is part of creating and editing records, in any module.
     */
    public function canWriteAnything(AdminUser $user): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        foreach ($user->adminPermissions() as $permission) {
            if (str_ends_with($permission, '.'.self::CREATE) || str_ends_with($permission, '.'.self::UPDATE)) {
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

        if (str_starts_with($routeName, 'admin.roles.')) {
            return self::SUPERADMIN;
        }

        if (str_starts_with($routeName, 'admin.api-tokens.')) {
            return self::API_TOKENS;
        }

        if ($routeName === 'admin.upload' || str_starts_with($routeName, 'admin.filemanager.')) {
            return self::ANY_WRITE;
        }

        if (preg_match('/^admin\.([a-z0-9_]+)\.([a-z]+)$/', $routeName, $match)
            && isset(self::ROUTE_ACTIONS[$match[2]])
            && isset($this->resources->modules()[$match[1]])) {
            return $match[1].'.'.self::ROUTE_ACTIONS[$match[2]];
        }

        return null;
    }
}
