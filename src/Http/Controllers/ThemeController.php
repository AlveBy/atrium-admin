<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\Themes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Keeps the colour theme on the admin's account, so it follows them to
 * another browser.
 */
final class ThemeController extends Controller
{
    public function update(Request $request, Themes $themes): RedirectResponse
    {
        $theme = $request->validate([
            'theme' => ['required', 'string', Rule::in($themes->choices())],
        ])['theme'];

        $user = $request->user(config('atrium.auth.guard', 'admin'));

        if ($user instanceof AdminUser) {
            $user->forceFill(['theme' => $theme])->save();
        }

        return back();
    }
}
