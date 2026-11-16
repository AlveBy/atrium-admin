<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Atrium\Admin\Http\Controllers\AdminUserController;
use Atrium\Admin\Http\Controllers\ApiTokenController;
use Atrium\Admin\Http\Controllers\Auth\LoginController;
use Atrium\Admin\Http\Controllers\CacheController;
use Atrium\Admin\Http\Controllers\DashboardController;
use Atrium\Admin\Http\Controllers\FileManagerController;
use Atrium\Admin\Http\Controllers\LinkController;
use Atrium\Admin\Http\Controllers\LocaleController;
use Atrium\Admin\Http\Controllers\ModuleController;
use Atrium\Admin\Http\Controllers\ProfileController;
use Atrium\Admin\Http\Controllers\ResourceController;
use Atrium\Admin\Http\Controllers\RoleController;
use Atrium\Admin\Http\Controllers\SiteController;
use Atrium\Admin\Http\Controllers\ThemeController;
use Atrium\Admin\Resources\ResourceRegistry;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Panel Routes
|--------------------------------------------------------------------------
|
| Here are the routes for the admin panel. They are wrapped in a route
| group with the admin prefix and web middleware.
|
*/

Route::prefix(config('atrium.prefix', 'admin'))
    ->middleware([...config('atrium.middleware', ['web']), 'atrium.site', 'atrium.inertia'])
    ->group(function () {

        // Interface language — for guests on the sign-in page too.
        Route::post('locale', [LocaleController::class, 'update'])->name('admin.locale');

        // Guest routes (login page)
        Route::middleware('admin.guest')->group(function () {
            Route::get('login', [LoginController::class, 'showLoginForm'])->name('admin.login');
            Route::post('login', [LoginController::class, 'login']);
        });

        // Authenticated routes; admin.can checks the administrator's roles.
        Route::middleware(['admin.auth', 'admin.can'])->group(function () {
            // Logout
            Route::post('logout', [LoginController::class, 'logout'])->name('admin.logout');

            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

            // Profile
            Route::get('profile', [ProfileController::class, 'edit'])->name('admin.profile.edit');
            Route::put('profile', [ProfileController::class, 'update'])->name('admin.profile.update');
            Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password');
            Route::put('profile/theme', [ThemeController::class, 'update'])->name('admin.profile.theme');

            // File Manager
            Route::post('upload', [FileManagerController::class, 'upload'])->name('admin.upload');
            Route::get('filemanager', [FileManagerController::class, 'index'])->name('admin.filemanager.index');
            Route::delete('filemanager', [FileManagerController::class, 'destroy'])->name('admin.filemanager.destroy');

            // The record picker of link fields (menus and the like)
            Route::get('links/records', [LinkController::class, 'records'])->name('admin.links.records');

            // The site the panel works with (header switch)
            Route::post('site', [SiteController::class, 'update'])->name('admin.site');

            // API tokens for external programs
            Route::get('api-tokens', [ApiTokenController::class, 'index'])->name('admin.api-tokens.index');
            Route::post('api-tokens', [ApiTokenController::class, 'store'])->name('admin.api-tokens.store');
            Route::delete('api-tokens/{id}', [ApiTokenController::class, 'destroy'])->whereNumber('id')->name('admin.api-tokens.destroy');

            // The record cache: hit rate, size, clearing — superadmins only
            Route::get('cache', [CacheController::class, 'index'])->name('admin.cache.index');
            Route::post('cache/clear', [CacheController::class, 'clear'])->name('admin.cache.clear');

            // Admin Users (Administrators)
            Route::resource('users', AdminUserController::class)->except(['show'])->names([
                'index' => 'admin.users.index',
                'create' => 'admin.users.create',
                'store' => 'admin.users.store',
                'edit' => 'admin.users.edit',
                'update' => 'admin.users.update',
                'destroy' => 'admin.users.destroy',
            ]);

            // Roles of administrators — superadmins only
            Route::resource('roles', RoleController::class)->except(['show'])->names('admin.roles');

            // Installed module packages and their settings
            Route::get('modules', [ModuleController::class, 'index'])->name('admin.modules.index');
            Route::get('modules/{module}/settings', [ModuleController::class, 'settings'])->name('admin.modules.settings');
            Route::put('modules/{module}/settings', [ModuleController::class, 'update'])->name('admin.modules.settings.update');
            Route::post('modules/{module}/enable', [ModuleController::class, 'enable'])->name('admin.modules.enable');
            Route::post('modules/{module}/disable', [ModuleController::class, 'disable'])->name('admin.modules.disable');

            // Modules from config('atrium.resources') — one generic controller.
            foreach (app(ResourceRegistry::class)->all() as $key => $model) {
                Route::resource($key, ResourceController::class)
                    ->except(['show'])
                    ->parameters([$key => 'id'])
                    ->names("admin.{$key}");
            }
        });
    });
