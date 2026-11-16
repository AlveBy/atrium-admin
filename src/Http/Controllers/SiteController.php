<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Http\Middleware\ResolveSite;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Switches the site the admin works with: lists and forms of modules that
 * belong to sites then show that site's records.
 */
final class SiteController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user(config('atrium.auth.guard', 'admin'));
        abort_unless($user instanceof AdminUser, 403);

        $id = $request->validate([
            'site_id' => ['required', Rule::in($user->allowedSites()->pluck('id')->map(fn ($id) => (string) $id)->all())],
        ])['site_id'];

        $request->session()->put(ResolveSite::SESSION_KEY, (int) $id);

        return redirect()->to($this->landing());
    }

    /**
     * Back where the admin was — but a record of the old site does not exist
     * on the new one, so from its form go to the module's list.
     */
    private function landing(): string
    {
        $previous = url()->previous();

        try {
            $route = Route::getRoutes()->match(Request::create($previous));
        } catch (Throwable) {
            return $previous;
        }

        if (preg_match('/^(admin\.[a-z0-9_]+)\.(edit|create)$/', (string) $route->getName(), $match) && Route::has($match[1].'.index')) {
            return route($match[1].'.index');
        }

        return $previous;
    }
}
