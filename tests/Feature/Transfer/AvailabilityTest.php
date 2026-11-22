<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Tests\Fixtures\Memo;
use Atrium\Admin\Tests\Fixtures\NotAdministrable;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Transfer\FrozenShelf;
use Atrium\Admin\Tests\Fixtures\Transfer\NoCreateShelf;
use Atrium\Admin\Tests\Fixtures\Transfer\NoExportShelf;
use Atrium\Admin\Tests\Fixtures\Transfer\NoImportShelf;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Transfer\Availability;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every module takes part in the import and the export unless its model says
 * otherwise; the routes follow.
 */
final class AvailabilityTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Parcel::class, Post::class, Memo::class, NoImportShelf::class, NoExportShelf::class, NoCreateShelf::class, FrozenShelf::class]);
    }

    /**
     * @return array<string,array{class-string,bool,bool}> model, importable, exportable
     */
    public static function models(): array
    {
        return [
            'a module that says nothing' => [Post::class, true, true],
            'one with fields of every kind' => [Parcel::class, true, true],
            'one that opts out of the import' => [NoImportShelf::class, false, true],
            'one that opts out of the export' => [NoExportShelf::class, true, false],
            'one nothing is created in' => [NoCreateShelf::class, false, true],
            'one nothing is changed in' => [FrozenShelf::class, false, true],
            'one written once (not editable)' => [Memo::class, false, true],
            'the sites' => [Site::class, false, true],
            'the languages' => [Language::class, false, true],
        ];
    }

    #[DataProvider('models')]
    public function test_the_models_own_answers_are_used(string $model, bool $import, bool $export): void
    {
        $this->assertSame($import, Availability::import($model));
        $this->assertSame($export, Availability::export($model));
        $this->assertSame($import, $model::isImportableInAdmin());
        $this->assertSame($export, $model::isExportableInAdmin());
    }

    public function test_a_model_without_the_methods_takes_part(): void
    {
        $this->assertFalse(method_exists(NotAdministrable::class, 'isImportableInAdmin'));
        $this->assertTrue(Availability::import(NotAdministrable::class));
        $this->assertTrue(Availability::export(NotAdministrable::class));
    }

    public function test_the_default_import_keys_are_the_id_and_the_code(): void
    {
        $this->assertSame(['id', 'code'], Post::getImportKeys());
        $this->assertSame(['article', 'code', 'id'], Parcel::getImportKeys());
    }

    #[DataProvider('models')]
    public function test_the_routes_are_there_for_the_ones_that_take_part_only(string $model, bool $import, bool $export): void
    {
        $key = app(ResourceRegistry::class)->keyOf($model);

        $this->assertNotNull($key);

        foreach (['import', 'import.store', 'import.show', 'import.check', 'import.run', 'import.step', 'import.cancel'] as $route) {
            $this->assertSame($import, Route::has("admin.{$key}.{$route}"), "admin.{$key}.{$route}");
        }

        $this->assertSame($export, Route::has("admin.{$key}.export"), "admin.{$key}.export");
    }

    public function test_the_routes_of_a_module_are_its_keys(): void
    {
        $this->assertSame('admin/parcels/import', Route::getRoutes()->getByName('admin.parcels.import')->uri());
        $this->assertSame('GET', Route::getRoutes()->getByName('admin.parcels.import')->methods()[0]);
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.parcels.import.store')->methods());
        $this->assertSame(['PUT'], Route::getRoutes()->getByName('admin.parcels.import.check')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.parcels.import.run')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.parcels.import.step')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.parcels.import.cancel')->methods());
        $this->assertSame('GET', Route::getRoutes()->getByName('admin.parcels.export')->methods()[0]);
        $this->assertSame('admin/parcels/export', Route::getRoutes()->getByName('admin.parcels.export')->uri());
        $this->assertSame('admin/parcels/import/{import}/run', Route::getRoutes()->getByName('admin.parcels.import.run')->uri());
        $this->assertSame('parcels', Route::getRoutes()->getByName('admin.parcels.import.run')->defaults['module']);
        $this->assertSame('posts', Route::getRoutes()->getByName('admin.posts.export')->defaults['module']);
    }
}
