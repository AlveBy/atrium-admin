<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\ConfigWriter;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\File;

final class CommandsTest extends TestCase
{
    public function test_atrium_user_creates_an_administrator(): void
    {
        $this->artisan('atrium:user', ['--name' => 'Ann', '--email' => 'ann@example.com', '--password' => 'long-enough'])
            ->assertSuccessful();

        $this->assertTrue(AdminUser::query()->where('email', 'ann@example.com')->exists());
    }

    public function test_the_first_administrator_is_a_superadmin_and_the_next_ones_are_not(): void
    {
        $this->artisan('atrium:user', ['--name' => 'Ann', '--email' => 'ann@example.com', '--password' => 'long-enough'])->assertSuccessful();
        $this->artisan('atrium:user', ['--name' => 'Bob', '--email' => 'bob@example.com', '--password' => 'long-enough'])->assertSuccessful();
        $this->artisan('atrium:user', ['--name' => 'Cid', '--email' => 'cid@example.com', '--password' => 'long-enough', '--superadmin' => true])->assertSuccessful();

        $this->assertTrue(AdminUser::query()->where('email', 'ann@example.com')->sole()->isSuperadmin());
        $this->assertFalse(AdminUser::query()->where('email', 'bob@example.com')->sole()->isSuperadmin());
        $this->assertTrue(AdminUser::query()->where('email', 'cid@example.com')->sole()->isSuperadmin());
    }

    public function test_atrium_user_superadmin_promotes_an_existing_administrator(): void
    {
        $this->admin();
        $this->artisan('atrium:user', ['--name' => 'Bob', '--email' => 'bob@example.com', '--password' => 'long-enough'])->assertSuccessful();

        $this->artisan('atrium:user', ['--email' => 'bob@example.com', '--superadmin' => true])->assertSuccessful();

        $this->assertTrue(AdminUser::query()->where('email', 'bob@example.com')->sole()->isSuperadmin());
        $this->assertSame(2, AdminUser::query()->count());
    }

    public function test_atrium_user_refuses_a_short_password(): void
    {
        $this->artisan('atrium:user', ['--name' => 'Ann', '--email' => 'ann@example.com', '--password' => 'short'])
            ->assertFailed();
    }

    public function test_make_module_writes_a_translatable_model_and_its_migration(): void
    {
        $model = app_path('Models/Brochure.php');
        $before = glob(database_path('migrations/*_create_brochures_table.php')) ?: [];
        // The command registers the module in the config of this very application.
        $config = config_path('atrium.php');
        $registered = is_file($config) ? file_get_contents($config) : null;

        try {
            $this->artisan('atrium:make-module', ['name' => 'Brochure'])->assertSuccessful();

            $code = (string) file_get_contents($model);
            $this->assertStringContainsString('use InteractsWithAdmin, Translatable;', $code);
            $this->assertStringContainsString("protected array \$translatable = ['name', 'code', 'preview_text', 'detail_text'];", $code);
            $this->assertStringContainsString("->unique('brochures', 'code')", $code);

            $migrations = array_diff(glob(database_path('migrations/*_create_brochures_table.php')) ?: [], $before);
            $this->assertCount(1, $migrations);
            $this->assertStringContainsString("\$table->json('name');", (string) file_get_contents((string) reset($migrations)));
        } finally {
            File::delete([$model, ...array_diff(glob(database_path('migrations/*_create_brochures_table.php')) ?: [], $before)]);

            if ($registered !== null) {
                file_put_contents($config, $registered);
            }
        }
    }

    public function test_config_writer_adds_a_module_once(): void
    {
        $path = sys_get_temp_dir().'/atrium-config-'.uniqid().'.php';
        File::put($path, "<?php\n\nreturn [\n    'resources' => [\n    ],\n];\n");

        $this->assertTrue(ConfigWriter::addResource('App\\Models\\Article', $path));
        $this->assertTrue(ConfigWriter::addResource('App\\Models\\Article', $path));

        $config = require $path;
        $this->assertSame(['App\\Models\\Article'], $config['resources']);

        File::delete($path);
    }

    public function test_config_writer_reports_a_config_it_cannot_edit(): void
    {
        $this->assertFalse(ConfigWriter::addResource('App\\Models\\Article', '/nonexistent/atrium.php'));
    }
}
