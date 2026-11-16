<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Resources\ResourceRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records of a module found by a few words, the way the module's own list
 * finds them: in the columns it marks searchable, every language of a
 * translated one, and by id. For pickers and the editors' search box.
 */
final class RecordSearch
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ContentLanguages $languages,
        private readonly Permissions $permissions,
    ) {}

    /**
     * @param  class-string<Model>  $model
     * @return Builder<Model>
     */
    public function query(string $model, string $text): Builder
    {
        $instance = new $model;
        $query = method_exists($instance, 'scopeAdminList') ? $instance->scopeAdminList($model::query()) : $model::query();
        $text = trim($text);

        if ($text === '') {
            return $query;
        }

        $columns = $this->searchableColumns($model);
        $codes = $this->languages->codes();

        // Nothing to look in: an empty group of conditions would find everything.
        if ($columns === [] && ! ctype_digit($text)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $where) use ($instance, $columns, $codes, $text): void {
            if (ctype_digit($text)) {
                $where->orWhere($instance->getQualifiedKeyName(), (int) $text);
            }

            foreach ($columns as $column) {
                if (method_exists($instance, 'isTranslatableAttribute') && $instance->isTranslatableAttribute($column)) {
                    $where->orWhere(fn (Builder $q) => $q->whereTranslationLike($column, $text, $codes));
                } else {
                    $where->orWhereRaw('lower('.$where->getGrammar()->wrap($column).') like lower(?)', ['%'.$text.'%']);
                }
            }
        });
    }

    /**
     * The name an editor knows the record by.
     */
    public function label(Model $record): string
    {
        $title = method_exists($record, 'getAdminTitle') ? $record->getAdminTitle() : $record->getKey();

        return Str::limit(trim(strip_tags((string) $title)), 120);
    }

    /**
     * Whether the administrator may see the module's records at all.
     */
    public function mayView(?AdminUser $user, string $model): bool
    {
        $key = $this->resources->keyOf($model);

        return $key !== null && ($user === null || $this->permissions->abilities($user, $key)['view']);
    }

    /**
     * The list columns the module marks searchable, without related ones.
     *
     * @return array<int,string>
     */
    public function searchableColumns(string $model): array
    {
        if (! method_exists($model, 'getAdministrationListColumns')) {
            return [];
        }

        $columns = [];

        foreach ($model::getAdministrationListColumns() as $column) {
            $data = $column->toArray();
            $name = $data['name'] ?? null;

            if (($data['searchable'] ?? false) && is_string($name) && $name !== '' && ! str_contains($name, '.')) {
                $columns[] = $name;
            }
        }

        return $columns;
    }
}
