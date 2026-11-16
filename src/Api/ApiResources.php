<?php

declare(strict_types=1);

namespace Atrium\Admin\Api;

use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Http\Controllers\ResourceController;
use Atrium\Admin\Resources\ResourceRegistry;
use Illuminate\Routing\Router;
use ReflectionClass;

/**
 * Which admin modules are visible through the API.
 *
 * The list is not maintained by hand but read from the admin routes: anything
 * registered as `admin.{key}.index` on an AdminController subclass is exposed
 * in the API under the same key. A new module from admin:make-module shows up
 * in the API and its documentation by itself — exactly what an agent that
 * knows nothing about the site needs.
 *
 * Exceptions are in `atrium.api.except`: administrators by default, they
 * cannot be created with a token.
 */
final class ApiResources
{
    /** @var array<string,class-string>|null */
    private ?array $resources = null;

    public function __construct(private readonly Router $router, private readonly ResourceRegistry $registry) {}

    /**
     * @return array<string,class-string> key => model class
     */
    public function all(): array
    {
        if ($this->resources !== null) {
            return $this->resources;
        }

        $except = config('atrium.api.except', ['users']);
        $resources = [];

        foreach ($this->router->getRoutes()->getRoutesByName() as $name => $route) {
            if (! preg_match('/^admin\.([a-z0-9_]+)\.index$/', $name, $match) || in_array($match[1], $except, true)) {
                continue;
            }

            $controller = $route->getControllerClass();

            if ($controller === null || ! is_subclass_of($controller, AdminController::class)) {
                continue;
            }

            // Generic modules carry no $model of their own — the registry knows it.
            $model = $controller === ResourceController::class
                ? $this->registry->model($match[1])
                : (new ReflectionClass($controller))->getProperty('model')->getDefaultValue();

            if (is_string($model) && class_exists($model)) {
                $resources[$match[1]] = $model;
            }
        }

        ksort($resources);

        return $this->resources = $resources;
    }

    /**
     * @return class-string|null
     */
    public function model(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Module name as it appears in the menu: "Catalog / Products".
     *
     * People do not recognise the model label — they look for a section by its
     * menu entry. A module that is not in the menu is labelled by its model.
     * Menu labels may be translation keys, hence __().
     */
    public function label(string $key): string
    {
        $route = "admin.{$key}.index";

        foreach (config('atrium.menu', []) as $item) {
            if (($item['route'] ?? null) === $route) {
                return __($item['label']);
            }

            foreach ($item['children'] ?? [] as $child) {
                if (($child['route'] ?? null) === $route) {
                    return __($item['label']).' / '.__($child['label']);
                }
            }
        }

        return $this->model($key)::getAdminLabel();
    }

    /**
     * The module key the model is exposed under — to refer to it when a field
     * has too many options for the documentation.
     */
    public function keyOf(string $model): ?string
    {
        $key = array_search($model, $this->all(), true);

        return $key === false ? null : $key;
    }

    /**
     * All abilities a token can be granted: "read" and "write" per module.
     *
     * @return array<int,string>
     */
    public function abilities(): array
    {
        $abilities = [];

        foreach (array_keys($this->all()) as $key) {
            $abilities[] = $key.':read';
            $abilities[] = $key.':write';
        }

        return $abilities;
    }
}
