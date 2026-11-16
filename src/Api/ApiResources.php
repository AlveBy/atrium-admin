<?php

declare(strict_types=1);

namespace Atrium\Admin\Api;

use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Http\Controllers\ResourceController;
use Atrium\Admin\Resources\ResourceRegistry;
use Illuminate\Routing\Router;
use ReflectionClass;

/**
 * Какие модули админки видны через API.
 *
 * Список не ведётся руками, а читается с маршрутов админки: всё, что
 * зарегистрировано как `admin.{ключ}.index` на наследнике AdminController,
 * открыто и в API под тем же ключом. Новый модуль из admin:make-module
 * появляется в API и в документации сам — ровно это и нужно агенту, который
 * про сайт ничего не знает.
 *
 * Исключения — `atrium.api.except`: по умолчанию там администраторы,
 * их через токен заводить нельзя.
 */
final class ApiResources
{
    /** @var array<string,class-string>|null */
    private ?array $resources = null;

    public function __construct(private readonly Router $router, private readonly ResourceRegistry $registry) {}

    /**
     * @return array<string,class-string> ключ => класс модели
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
     * Название модуля так, как его видно в меню: «Направления / Товары».
     *
     * Подпись модели («Каталог») заказчик не узнаёт — он ищет раздел по
     * пункту меню. Модуль, которого в меню нет, подписан именем модели.
     */
    public function label(string $key): string
    {
        $route = "admin.{$key}.index";

        foreach (config('atrium.menu', []) as $item) {
            if (($item['route'] ?? null) === $route) {
                return $item['label'];
            }

            foreach ($item['children'] ?? [] as $child) {
                if (($child['route'] ?? null) === $route) {
                    return $item['label'].' / '.$child['label'];
                }
            }
        }

        return $this->model($key)::getAdminLabel();
    }

    /**
     * Ключ модуля, которым открыта модель, — чтобы сослаться на него, когда
     * вариантов поля слишком много для документации.
     */
    public function keyOf(string $model): ?string
    {
        $key = array_search($model, $this->all(), true);

        return $key === false ? null : $key;
    }

    /**
     * Все права, какие можно выдать токену: «читать» и «менять» на модуль.
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
