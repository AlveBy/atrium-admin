<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Atrium\Admin\Http\Controllers\AdminUserController;
use Atrium\Admin\Http\Controllers\ApiTokenController;
use Atrium\Admin\Http\Controllers\Auth\LoginController;
use Atrium\Admin\Http\Controllers\Auth\TwoFactorController;
use Atrium\Admin\Http\Controllers\DashboardController;
use Atrium\Admin\Http\Controllers\FileManagerController;
use Atrium\Admin\Http\Controllers\LocaleController;
use Atrium\Admin\Http\Controllers\ProfileController;
use Atrium\Admin\Http\Controllers\ResourceController;
use Atrium\Admin\Http\Controllers\RoleController;
use Atrium\Admin\Http\Controllers\SiteController;
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

            // Second sign-in step: the password is accepted, waiting for the
            // emailed code. Also for guests — there is no session before the code.
            Route::get('two-factor', [TwoFactorController::class, 'show'])->name('admin.two-factor');
            Route::post('two-factor', [TwoFactorController::class, 'store']);
            Route::post('two-factor/resend', [TwoFactorController::class, 'resend'])->name('admin.two-factor.resend');
            Route::post('two-factor/cancel', [TwoFactorController::class, 'cancel'])->name('admin.two-factor.cancel');
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

            // File Manager
            Route::post('upload', [FileManagerController::class, 'upload'])->name('admin.upload');
            Route::get('filemanager', [FileManagerController::class, 'index'])->name('admin.filemanager.index');
            Route::delete('filemanager', [FileManagerController::class, 'destroy'])->name('admin.filemanager.destroy');

            // The site the panel works with (header switch)
            Route::post('site', [SiteController::class, 'update'])->name('admin.site');

            // API tokens for external programs
            Route::get('api-tokens', [ApiTokenController::class, 'index'])->name('admin.api-tokens.index');
            Route::post('api-tokens', [ApiTokenController::class, 'store'])->name('admin.api-tokens.store');
            Route::delete('api-tokens/{id}', [ApiTokenController::class, 'destroy'])->name('admin.api-tokens.destroy');

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

            // Modules from config('atrium.resources') — one generic controller.
            foreach (app(ResourceRegistry::class)->all() as $key => $model) {
                Route::resource($key, ResourceController::class)
                    ->except(['show'])
                    ->parameters([$key => 'id'])
                    ->names("admin.{$key}");
            }
        });
    });
