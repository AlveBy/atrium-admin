<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Fields\LinkField;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\RecordSearch;
use Atrium\Admin\Support\RecordUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The record picker of LinkField: records of one module by a few words, with
 * their names and addresses. Only modules whose records have pages, only to
 * administrators who may see that module.
 */
final class LinkController
{
    private const LIMIT = 20;

    public function records(Request $request, RecordSearch $search, RecordUrls $urls): JsonResponse
    {
        $model = $request->query('model');
        $text = $request->query('q');
        /** @var AdminUser $user behind the admin's auth middleware */
        $user = $request->user(config('atrium.auth.guard', 'admin'));

        abort_unless(is_string($model) && array_key_exists($model, LinkField::make('link')->targets()), 404);
        abort_unless($search->mayView($user, $model), 403, __('atrium::permissions.forbidden'));

        /** @var class-string<Model> $model */
        $records = $search->query($model, is_string($text) ? $text : '')
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'data' => $records->map(fn (Model $record): array => [
                'id' => $record->getKey(),
                'label' => $search->label($record),
                'url' => $urls->url($record),
            ])->values(),
        ]);
    }
}
