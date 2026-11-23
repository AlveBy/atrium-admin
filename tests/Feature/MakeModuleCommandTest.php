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
        $this->app->useLangPath($this->root.'/lang');
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
        $this->assertStringContainsString("return __('price_lists.module');", $model);
        $this->assertStringNotContainsString('Dealer', $model, 'the name is in the language file, not in the model');
        $this->assertSame(['module' => "Dealer's prices"], require $this->root.'/lang/en/price_lists.php');
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
        $this->assertStringContainsString("return __('price_lists.module');", $model);
        $this->assertSame(['module' => 'Price Lists'], require $this->root.'/lang/en/price_lists.php');
        $this->assertStringContainsString("->unique('price_lists', 'code')", $model);

        $migrations = glob($this->root.'/database/migrations/*') ?: [];
        $this->assertCount(1, $migrations);
        $this->assertMatchesRegularExpression('#/\d{4}_\d{2}_\d{2}_\d{6}_create_price_lists_table\.php$#', $migrations[0]);
        $migration = (string) file_get_contents($migrations[0]);
        $this->assertStringContainsString("Schema::create('price_lists'", $migration);
        $this->assertStringContainsString("Schema::dropIfExists('price_lists')", $migration);
        $this->assertStringNotContainsString('{{', $model.$migration);
    }

    public function test_a_quote_and_a_backslash_in_the_label_are_escaped_and_an_empty_label_falls_back_to_the_plural(): void
    {
        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--label' => "A\\B'C"])->assertSuccessful();
        $this->assertSame(['module' => "A\\B'C"], require $this->root.'/lang/en/brochures.php');
        $this->assertStringContainsString("'A\\\\B\\'C'", (string) file_get_contents($this->root.'/lang/en/brochures.php'));

        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--label' => '', '--force' => true])->assertSuccessful();
        $this->assertSame(['module' => 'Brochures'], require $this->root.'/lang/en/brochures.php');
    }

    public function test_the_name_goes_to_the_file_of_the_fallback_language(): void
    {
        config(['app.fallback_locale' => 'de']);

        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--label' => 'Broschüren', '--no-migration' => true])
            ->expectsOutputToContain('Name ['.$this->root.'/lang/de/brochures.php] created.')
            ->assertSuccessful();

        $this->assertSame(['module' => 'Broschüren'], require $this->root.'/lang/de/brochures.php');
        $this->assertFileDoesNotExist($this->root.'/lang/en/brochures.php');
    }

    public function test_a_language_file_that_is_there_is_kept_unless_forced(): void
    {
        mkdir($this->root.'/lang/en', 0777, true);
        file_put_contents($this->root.'/lang/en/brochures.php', '<?php return ["module" => "Mine"];');

        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--label' => 'Leaflets', '--no-migration' => true])
            ->expectsOutputToContain('already exists')
            ->doesntExpectOutputToContain('Name [')
            ->assertSuccessful();

        $this->assertSame('<?php return ["module" => "Mine"];', file_get_contents($this->root.'/lang/en/brochures.php'));
        $this->assertFileExists($this->root.'/app/Models/Brochure.php');

        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--label' => 'Leaflets', '--no-migration' => true, '--force' => true])
            ->doesntExpectOutputToContain('already exists')
            ->assertSuccessful();

        $this->assertSame(['module' => 'Leaflets'], require $this->root.'/lang/en/brochures.php');
    }

    public function test_the_generated_model_speaks_the_language_of_the_panel(): void
    {
        $this->artisan('atrium:make-module', ['name' => 'Handout', '--label' => 'Handouts', '--no-migration' => true])->assertSuccessful();
        mkdir($this->root.'/lang/ru', 0777, true);
        file_put_contents($this->root.'/lang/ru/handouts.php', "<?php return ['module' => 'Раздатка'];");
        $this->app->make('translator')->getLoader()->addPath($this->root.'/lang');

        require_once $this->root.'/app/Models/Handout.php';
        $model = 'App\\Models\\Handout';

        $this->app->setLocale('en');
        $this->assertSame('Handouts', $model::getAdminLabel());
        $this->assertSame('Name', $model::getAdministrationFields()[0]->toArray()['label']);
        $this->assertSame('Name', $model::getAdministrationListColumns()[1]->toArray()['label']);

        $this->app->setLocale('ru');
        $this->assertSame('Раздатка', $model::getAdminLabel());
        $this->assertSame('Название', $model::getAdministrationFields()[0]->toArray()['label']);
        $this->assertSame('Название', $model::getAdministrationListColumns()[1]->toArray()['label']);

        $this->app->setLocale('de');
        $this->assertSame('Handouts', $model::getAdminLabel(), 'a language without a file reads the fallback one');
    }

    public function test_no_label_of_the_generated_model_is_written_in_one_language(): void
    {
        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--no-migration' => true])->assertSuccessful();

        $model = (string) file_get_contents($this->root.'/app/Models/Brochure.php');
        $this->assertSame(0, preg_match("/->label\\('/", $model), 'every label goes through __()');
        $this->assertSame(0, preg_match("/return '[A-Z]/", $model));
    }

    public function test_a_stub_of_the_application_wins_over_the_packaged_one(): void
    {
        $this->app->setBasePath($this->root);
        mkdir($this->root.'/stubs/atrium', 0777, true);
        file_put_contents($this->root.'/stubs/atrium/model.stub', "<?php // {{ class }} in {{ namespace }} of {{ table }} named '{{ label }}'");
        file_put_contents($this->root.'/stubs/atrium/lang.stub', '<?php // {{ label }}');

        $this->artisan('atrium:make-module', ['name' => 'Brochure', '--label' => "Fly'er", '--no-migration' => true])->assertSuccessful();

        $this->assertSame("<?php // Brochure in App\\Models of brochures named 'Fly\\'er'", file_get_contents($this->root.'/app/Models/Brochure.php'), 'a stub written for the old generator still gets the label');
        $this->assertSame("<?php // Fly\\'er", file_get_contents($this->root.'/lang/en/brochures.php'));
    }
}
