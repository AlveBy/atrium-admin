<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use ReflectionProperty;

/**
 * atrium:make-module writes a model, a migration and the config entry, and
 * does not overwrite a model that is already there unless told to.
 */
final class MakeModuleCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/atrium-make-module-'.getmypid().'-'.uniqid();
        foreach (['app', 'config', 'database/migrations'] as $dir) {
            mkdir("{$this->root}/{$dir}", 0777, true);
        }
        $this->app->useAppPath($this->root.'/app');
        $this->app->useConfigPath($this->root.'/config');
        $this->app->useDatabasePath($this->root.'/database');
        // The namespace is read from composer.json against the app path, which now points elsewhere.
        (new ReflectionProperty($this->app, 'namespace'))->setValue($this->app, 'App\\');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_a_module_is_written_and_registered_in_the_config(): void
    {
        file_put_contents($this->root.'/config/atrium.php', "<?php\n\nreturn [\n    'resources' => [\n    ],\n];\n");

        $this->artisan('atrium:make-module', ['name' => 'price list', '--label' => "Dealer's prices"])
            ->expectsOutputToContain('Module registered in [config/atrium.php].')
            ->doesntExpectOutputToContain('Add App\\Models\\PriceList::class')
            ->assertSuccessful();

        $model = (string) file_get_contents($this->root.'/app/Models/PriceList.php');
        $this->assertStringContainsString('class PriceList extends Model', $model);
        $this->assertStringContainsString("Dealer\\'s prices", $model);
        $this->assertCount(1, glob($this->root.'/database/migrations/*_create_price_lists_table.php') ?: []);
        $this->assertSame(['App\\Models\\PriceList'], (require $this->root.'/config/atrium.php')['resources']);
    }

    public function test_without_a_published_config_it_says_what_to_add(): void
    {
        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--no-migration' => true])
            ->expectsOutputToContain("Add App\\Models\\Brochure::class to 'resources' in config/atrium.php")
            ->doesntExpectOutputToContain('Module registered')
            ->assertSuccessful();

        $this->assertFileExists($this->root.'/app/Models/Brochure.php');
        $this->assertSame([], glob($this->root.'/database/migrations/*') ?: []);
        $this->assertFileDoesNotExist($this->root.'/config/atrium.php');
    }

    public function test_a_model_that_exists_is_kept_unless_forced(): void
    {
        mkdir($this->root.'/app/Models');
        file_put_contents($this->root.'/app/Models/Brochure.php', '<?php // mine');

        $this->artisan('atrium:make-module', ['name' => 'Brochure'])
            ->expectsOutputToContain('already exists. Use --force to overwrite.')
            ->doesntExpectOutputToContain('created.')
            ->assertFailed();

        $this->assertSame('<?php // mine', file_get_contents($this->root.'/app/Models/Brochure.php'));
        $this->assertSame([], glob($this->root.'/database/migrations/*') ?: []);

        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--force' => true])->assertSuccessful();

        $this->assertStringContainsString('class Brochure extends Model', (string) file_get_contents($this->root.'/app/Models/Brochure.php'));
        $this->assertCount(1, glob($this->root.'/database/migrations/*_create_brochures_table.php') ?: []);
    }

    public function test_the_model_and_the_migration_carry_the_namespace_table_label_and_are_announced(): void
    {
        $this->artisan('atrium:make-module', ['name' => 'price list'])
            ->expectsOutputToContain('Model ['.$this->root.'/app/Models/PriceList.php] created.')
            ->expectsOutputToContain('Migration ['.$this->root.'/database/migrations/')
            ->assertSuccessful();

        $model = (string) file_get_contents($this->root.'/app/Models/PriceList.php');
        $this->assertStringContainsString("\nnamespace App\\Models;\n", $model);
        $this->assertStringContainsString("return 'Price Lists';", $model);
        $this->assertStringContainsString("->unique('price_lists', 'code')", $model);

        $migrations = glob($this->root.'/database/migrations/*') ?: [];
        $this->assertCount(1, $migrations);
        $this->assertMatchesRegularExpression('#/\d{4}_\d{2}_\d{2}_\d{6}_create_price_lists_table\.php$#', $migrations[0]);
        $migration = (string) file_get_contents($migrations[0]);
        $this->assertStringContainsString("Schema::create('price_lists'", $migration);
        $this->assertStringContainsString("Schema::dropIfExists('price_lists')", $migration);
        $this->assertStringNotContainsString('{{', $model.$migration);
    }

    public function test_a_backslash_in_the_label_is_escaped_and_an_empty_label_falls_back_to_the_plural(): void
    {
        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--label' => 'A\\B'])->assertSuccessful();
        $this->assertStringContainsString("return 'A\\\\B';", (string) file_get_contents($this->root.'/app/Models/Brochure.php'));

        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--label' => '', '--force' => true])->assertSuccessful();
        $this->assertStringContainsString("return 'Brochures';", (string) file_get_contents($this->root.'/app/Models/Brochure.php'));
    }

    public function test_a_stub_of_the_application_wins_over_the_packaged_one(): void
    {
        $this->app->setBasePath($this->root);
        mkdir($this->root.'/stubs/atrium', 0777, true);
        file_put_contents($this->root.'/stubs/atrium/model.stub', '<?php // {{ class }} in {{ namespace }} of {{ table }}');

        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--no-migration' => true])->assertSuccessful();

        $this->assertSame('<?php // Brochure in App\\Models of brochures', file_get_contents($this->root.'/app/Models/Brochure.php'));
    }
}
