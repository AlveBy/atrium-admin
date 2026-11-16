<?php

declare(strict_types=1);

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
