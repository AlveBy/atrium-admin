<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Modules\Module;
use Atrium\Admin\Tests\TestCase;
use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Process\Process;

/**
 * atrium:make-package: a module package of its own, from stubs/module, whose
 * tests pass as they come.
 */
final class MakePackageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/atrium-make-package-'.uniqid();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_makes_a_module_package_with_the_names_filled_in(): void
    {
        $this->artisan('atrium:make-package', ['package' => 'acme/atrium-reviews', '--path' => $this->root.'/reviews'])
            ->expectsOutputToContain('composer require acme/atrium-reviews:@dev')
            ->assertSuccessful();

        $path = $this->root.'/reviews';
        $files = $this->files($path);

        $this->assertSame([
            '.gitattributes',
            '.github/workflows/tests.yml',
            '.gitignore',
            'LICENSE',
            'README.md',
            'composer.json',
            'database/migrations/'.date('Y_m_d').'_000001_create_reviews_table.php',
            'lang/en/reviews.php',
            'phpunit.xml.dist',
            'src/Database/ReviewsDemoSeeder.php',
            'src/Models/Review.php',
            'src/ReviewsServiceProvider.php',
            'tests/Feature/AdminTest.php',
            'tests/Feature/DemoTest.php',
            'tests/TestCase.php',
        ], $files);

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/\{\{ [a-z_]+ \}\}/', (string) file_get_contents("{$path}/{$file}"), "{$file} has a placeholder left");
        }

        $module = Module::fromPath($path);
        $this->assertNotNull($module);
        $this->assertSame('acme/atrium-reviews', $module->name);
        $this->assertSame('reviews', $module->key());
        $this->assertSame('Reviews', $module->label());
        $this->assertSame(['Acme\\Reviews\\Models\\Review'], $module->resources);
        $this->assertSame('Acme\\Reviews\\Database\\ReviewsDemoSeeder', $module->demo);
        $this->assertSame([$path.'/database/migrations'], $module->migrations);
        $this->assertStringContainsString("protected \$table = 'reviews';", (string) file_get_contents($path.'/src/Models/Review.php'));
    }

    public function test_the_package_tests_pass_as_they_come(): void
    {
        $this->artisan('atrium:make-package', [
            'package' => ' Acme/Atrium-Shop-Extras ',
            '--model' => 'Badge',
            '--namespace' => '\\Acme/ShopExtras\\',
            '--title' => 'Shop "badges" it\'s \\',
            '--path' => $this->root.'/extras',
        ])->assertSuccessful();

        $path = $this->root.'/extras';
        $this->assertStringContainsString("protected \$table = 'shop_extras_badges';", (string) file_get_contents($path.'/src/Models/Badge.php'));
        $module = Module::fromPath($path);
        $this->assertSame('acme/atrium-shop-extras', $module?->name);
        $this->assertSame('Shop badges its ', $module->label());
        $this->assertSame(['Acme\\ShopExtras\\Models\\Badge'], $module->resources);

        // The package's own suite, with this repository's vendor folder in
        // place of its own `composer install`.
        $vendor = dirname((string) (new ReflectionClass(ClassLoader::class))->getFileName(), 2);
        file_put_contents($this->root.'/bootstrap.php', '<?php $loader = require '.var_export($vendor.'/autoload.php', true).';'
            .' $loader->addPsr4("Acme\\\\ShopExtras\\\\", '.var_export($path.'/src/', true).');'
            .' $loader->addPsr4("Acme\\\\ShopExtras\\\\Tests\\\\", '.var_export($path.'/tests/', true).');');

        $process = new Process([PHP_BINARY, $vendor.'/bin/phpunit', '--bootstrap', $this->root.'/bootstrap.php', '-c', $path.'/phpunit.xml.dist', '--no-coverage'], $path, ['DB_CONNECTION' => false]);
        $process->setTimeout(120)->run();

        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
    }

    public function test_it_refuses_a_bad_name_and_a_folder_that_is_not_empty(): void
    {
        $this->artisan('atrium:make-package', ['package' => 'Reviews', '--path' => $this->root.'/bad'])
            ->expectsOutputToContain('reviews is not a Composer package name')
            ->assertFailed();
        $this->assertDirectoryDoesNotExist($this->root.'/bad');

        mkdir($this->root.'/taken', 0777, true);
        file_put_contents($this->root.'/taken/keep.txt', 'mine');

        $this->artisan('atrium:make-package', ['package' => 'acme/atrium-reviews', '--path' => $this->root.'/taken'])
            ->expectsOutputToContain('is not empty')
            ->assertFailed();
        $this->assertSame(['keep.txt'], $this->files($this->root.'/taken'));
    }

    public function test_it_goes_into_packages_and_says_how_to_try_it(): void
    {
        // A name of its own: tests of other processes may run at the same time.
        $name = 'tmp'.uniqid();
        $package = "acme/atrium-{$name}";
        $path = base_path("packages/{$name}");

        try {
            $this->artisan('atrium:make-package', ['package' => $package])
                ->expectsOutputToContain("{$package} is in")
                ->expectsOutputToContain('Try it in this project:')
                ->expectsOutputToContain("composer config repositories.{$name} path packages/{$name}")
                ->expectsOutputToContain("composer require {$package}:@dev")
                ->expectsOutputToContain("php artisan atrium:module:install {$package} --demo")
                ->expectsOutputToContain("Its own tests: cd packages/{$name} && composer install && vendor/bin/phpunit")
                ->assertSuccessful();

            $this->assertSame(Str::headline($name), Module::fromPath($path)?->label());
        } finally {
            (new Filesystem)->deleteDirectory($path);
        }
    }

    /**
     * @return array<int,string>
     */
    private function files(string $path): array
    {
        $files = array_map(static fn (\SplFileInfo $file): string => substr($file->getPathname(), strlen($path) + 1), (new Filesystem)->allFiles($path, true));
        sort($files);

        return $files;
    }
}
