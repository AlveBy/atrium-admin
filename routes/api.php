<?php

declare(strict_types=1);

use Atrium\Admin\Http\Controllers\Api\DocsController;
use Atrium\Admin\Http\Controllers\Api\ResourceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API модулей админки
|--------------------------------------------------------------------------
|
| Документация открыта, всё остальное — по токену из раздела «API-токены».
| Модули не перечисляются: {resource} — ключ любого модуля админки, список
| собирает ApiResources с маршрутов admin-modules.php.
|
*/

Route::prefix(config('atrium.api.prefix', 'api/v1'))
    ->middleware('api')
    ->group(function () {
        Route::get('/', [DocsController::class, 'markdown'])->name('admin.api.docs');
        Route::get('openapi.json', [DocsController::class, 'openApi'])->name('admin.api.openapi');

        Route::middleware('admin.api')->where(['resource' => '[a-z0-9_]+', 'id' => '[0-9]+'])->group(function () {
            Route::post('files', [ResourceController::class, 'upload'])->name('admin.api.files');

            Route::get('{resource}', [ResourceController::class, 'listItems'])->name('admin.api.list');
            Route::post('{resource}', [ResourceController::class, 'storeItem'])->name('admin.api.store');
            Route::get('{resource}/{id}', [ResourceController::class, 'showItem'])->name('admin.api.show');
            Route::match(['put', 'patch'], '{resource}/{id}', [ResourceController::class, 'updateItem'])->name('admin.api.update');
            Route::delete('{resource}/{id}', [ResourceController::class, 'destroyItem'])->name('admin.api.destroy');
        });
    });
