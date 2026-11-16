<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Switches the panel language. A signed-in admin keeps the choice on the
 * account (it follows them to another browser); a guest on the sign-in page
 * keeps it in the session until they sign in.
 */
final class LocaleController extends Controller
{
    public function update(Request $request, Locales $locales): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys($locales->available()))],
        ])['locale'];

        $request->session()->put(Locales::SESSION_KEY, $locale);

        $user = auth()->guard(config('atrium.auth.guard', 'admin'))->user();

        if ($user instanceof AdminUser) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
