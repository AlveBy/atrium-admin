<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Dashboard\Gadgets;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Dashboard\Gadget;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * "Recent items" — the most recently changed items of one module.
 *
 * Mirrors bitrix/gadgets/bitrix/admin_iblock_elements: date, preview picture,
 * title linking to the edit form and "All items" below.
 *
 * The Bitrix gadget draws nothing without an IBLOCK_ID in its settings, and in
 * practice it often sat unconfigured as an empty frame with a title. So we take
 * the idea of the gadget and set the model in the config.
 *
 * Sorted by "updated", not by ID as Bitrix does by default: the gadget exists
 * to get back to what you edited yesterday, not to what you created.
 */
final class RecentElementsGadget extends Gadget
{
    public function component(): string
    {
        return 'RecentElementsGadget';
    }

    public function title(): string
    {
        $title = $this->option('title');

        if (is_string($title) && $title !== '') {
            return $title;
        }

        $model = $this->modelClass();

        if ($model !== null && method_exists($model, 'getAdminLabel')) {
            return (string) $model::getAdminLabel();
        }

        return __('atrium::dashboard.recent.title');
    }

    public function isVisible(): bool
    {
        $model = $this->modelClass();

        return parent::isVisible() && $model !== null && $this->viewable($model);
    }

    /**
     * Records of a module the administrator may not open stay off their
     * dashboard too.
     *
     * @param  class-string<Model>  $model
     */
    private function viewable(string $model): bool
    {
        $user = auth()->guard(config('atrium.auth.guard', 'admin'))->user();

        if (! $user instanceof AdminUser || ! method_exists($model, 'getAdminRouteName')) {
            return true;
        }

        return app(Permissions::class)->allowsRoute($user, $model::getAdminRouteName().'.index');
    }

    public function data(): array
    {
        $model = $this->modelClass();

        if ($model === null) {
            return ['items' => [], 'allUrl' => null, 'allLabel' => __('atrium::dashboard.recent.all')];
        }

        return [
            'items' => $this->items($model),
            'allUrl' => $this->routeTo($model, 'index'),
            'allLabel' => __('atrium::dashboard.recent.all'),
        ];
    }

    /** @return class-string<Model>|null */
    private function modelClass(): ?string
    {
        $class = $this->option('model');

        return is_string($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function items(string $model): array
    {
        $count = max(1, min((int) $this->option('count', 5), 20));

        try {
            /** @var Model $sample */
            $sample = new $model;
            $table = $sample->getTable();

            $sortColumn = $sample->usesTimestamps() && Schema::hasColumn($table, $sample->getUpdatedAtColumn())
                ? $sample->getUpdatedAtColumn()
                : $sample->getKeyName();

            $pictureColumn = $this->firstExistingColumn($table, ['preview_picture', 'detail_picture']);

            return $model::query()
                ->orderByDesc($sortColumn)
                ->orderByDesc($sample->getKeyName())
                ->limit($count)
                ->get()
                ->map(fn (Model $item) => [
                    'id' => $item->getKey(),
                    'name' => (string) ($item->getAttribute('name') ?: '#'.$item->getKey()),
                    'date' => $this->formatDate($item->getAttribute($sortColumn)),
                    'image' => $pictureColumn ? $this->imageUrl($item->getAttribute($pictureColumn)) : null,
                    'url' => $this->routeTo($model, 'edit', $item->getKey()),
                ])
                ->all();
        } catch (Throwable) {
            // The table may be missing (the model exists, the migration has not
            // run) — the dashboard must not fail because of one tile.
            return [];
        }
    }

    /** @param array<int,string> $candidates */
    private function firstExistingColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function formatDate(mixed $value): ?string
    {
        if (! $value instanceof \DateTimeInterface) {
            return null;
        }

        // "3 August 2026" — like FormatDate("j F Y") in the Bitrix gadget, with
        // month names in the interface language.
        return Carbon::instance($value)->locale(app()->getLocale())->translatedFormat('j F Y');
    }

    private function imageUrl(mixed $value): ?string
    {
        // Shown small: 2× for sharp screens.
        return MediaUrl::to($value, 160);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function routeTo(string $model, string $action, mixed $key = null): ?string
    {
        if (! method_exists($model, 'getAdminRouteName')) {
            return null;
        }

        $name = $model::getAdminRouteName().'.'.$action;

        if (! Route::has($name)) {
            return null;
        }

        return $key === null ? route($name) : route($name, $key);
    }
}
