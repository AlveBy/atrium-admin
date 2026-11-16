<?php

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Resources\ResourceRegistry;

/**
 * The sidebar: `atrium.menu` as written, plus every module nobody put there.
 *
 * A module added to `atrium.resources` shows up in the menu without touching
 * the menu config; listing it in `atrium.menu` only decides where and under
 * which label.
 */
final class Menu
{
    public function __construct(private readonly ResourceRegistry $resources) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function items(): array
    {
        $menu = (array) config('atrium.menu', []);
        $listed = $this->listedRoutes($menu);

        $modules = [];

        foreach ($this->resources->all() as $key => $model) {
            $route = "admin.{$key}.index";

            if (! in_array($route, $listed, true)) {
                $modules[] = [
                    'label' => $model::getAdminLabel(),
                    'route' => $route,
                    'icon' => 'folder',
                ];
            }
        }

        if ($modules === []) {
            return $menu;
        }

        // Modules go right after the first item (the dashboard), above the
        // system entries such as administrators and API tokens.
        array_splice($menu, min(1, count($menu)), 0, $modules);

        return $menu;
    }

    /**
     * @param  array<int,array<string,mixed>>  $menu
     * @return array<int,string>
     */
    private function listedRoutes(array $menu): array
    {
        $routes = [];

        foreach ($menu as $item) {
            if (isset($item['route'])) {
                $routes[] = (string) $item['route'];
            }

            foreach ($item['children'] ?? [] as $child) {
                if (isset($child['route'])) {
                    $routes[] = (string) $child['route'];
                }
            }
        }

        return $routes;
    }
}
