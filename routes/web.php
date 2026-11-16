<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Atrium\Admin\Http\Controllers\CaptchaController;
use Atrium\Admin\Http\Controllers\SeoFilesController;
use Illuminate\Support\Facades\Route;

// Public files of every site, built from what is set in the admin. No session:
// robots and sitemaps are fetched by crawlers, not by people.
Route::middleware('atrium.site')->group(function (): void {
    // Off — the application's own public/robots.txt is served instead.
    if (config('atrium.robots', true)) {
        Route::get('robots.txt', [SeoFilesController::class, 'robots'])->name('atrium.robots');
    }

    if (config('atrium.sitemap.enabled', true)) {
        Route::get('sitemap.xml', [SeoFilesController::class, 'sitemap'])->name('atrium.sitemap');
        Route::get('sitemap-{part}.xml', [SeoFilesController::class, 'sitemapPart'])->whereNumber('part')->name('atrium.sitemap.part');
    }
});

// The picture of the built-in captcha (atrium.captcha.driver = image); the
// question is encrypted in the address, nothing is kept on the server.
Route::get('atrium/captcha.png', [CaptchaController::class, 'image'])->name('atrium.captcha.image');
