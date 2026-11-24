<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Embeds\Embeds;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the editor's "Insert" button offers for one embed type: its items by
 * a few typed words (`q`), or the names of known ids (`ids=3,7`) to label the
 * chips of a text that is already there. Only to those the type is open to.
 */
final class EmbedController
{
    public function __invoke(Request $request, Embeds $embeds, string $type): JsonResponse
    {
        $embed = $embeds->get($type);
        /** @var AdminUser $user behind the admin's auth middleware */
        $user = $request->user(config('atrium.auth.guard', 'admin'));

        abort_if($embed === null, 404);
        abort_unless($embed->permitted($user), 403, __('atrium::permissions.forbidden'));

        $ids = $request->query('ids');
        $text = $request->query('q');

        return response()->json([
            'data' => is_string($ids)
                ? $embed->labels(explode(',', $ids))
                : $embed->options(is_string($text) ? $text : ''),
        ]);
    }
}
