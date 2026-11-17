<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Support\ConfigWriter;
use PHPUnit\Framework\TestCase;

final class ConfigWriterTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/atrium-config-'.bin2hex(random_bytes(6)).'.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function config(string $body): void
    {
        file_put_contents($this->path, "<?php\n\nreturn [\n{$body}];\n");
    }

    /**
     * @return array<string,mixed>
     */
    private function read(): array
    {
        return require $this->path;
    }

    public function test_a_resource_goes_first_in_the_list(): void
    {
        $this->config("    'resources' => [\n        \\App\\Models\\Page::class,\n    ],\n");

        $this->assertTrue(ConfigWriter::addResource('\\App\\Models\\Article', $this->path));

        $this->assertSame(['App\\Models\\Article', 'App\\Models\\Page'], $this->read()['resources']);
    }

    public function test_a_config_without_a_resources_list_is_left_alone(): void
    {
        $this->config("    'prefix' => 'admin',\n    'resources' => [App\\Models\\Page::class],\n");
        $before = (string) file_get_contents($this->path);

        $this->assertFalse(ConfigWriter::addResource('App\\Models\\Article', $this->path));
        $this->assertSame($before, file_get_contents($this->path));
    }

    public function test_modules_are_kept_in_lower_case_in_the_order_added(): void
    {
        $this->config("    'resources' => [\n    ],\n\n    'modules' => [\n        'acme/one',\n    ],\n");

        $this->assertTrue(ConfigWriter::addModule('Acme/Two', $this->path));
        $this->assertTrue(ConfigWriter::addModule('ACME/ONE', $this->path));

        $this->assertSame(['acme/one', 'acme/two'], $this->read()['modules']);
    }

    public function test_a_config_with_neither_the_list_nor_the_one_before_it_is_left_alone(): void
    {
        $this->config("    'prefix' => 'admin',\n");
        $before = (string) file_get_contents($this->path);

        $this->assertFalse(ConfigWriter::addModule('acme/one', $this->path));
        // Switching off needs the modules list to put the new list after.
        $this->assertFalse(ConfigWriter::disableModule('acme/one', $this->path));
        $this->assertSame($before, file_get_contents($this->path));
    }

    public function test_a_switched_off_module_gets_its_list_after_the_modules(): void
    {
        $this->config("    'modules' => [\n        'acme/one',\n    ],\n\n    'demo' => false,\n");

        $this->assertTrue(ConfigWriter::disableModule('Acme/One', $this->path));
        $this->assertTrue(ConfigWriter::disableModule('acme/one', $this->path));

        $config = $this->read();
        $this->assertSame(['acme/one'], $config['disabled_modules']);
        $this->assertSame(['acme/one'], $config['modules']);
        $this->assertFalse($config['demo']);
        $this->assertSame(['modules', 'disabled_modules', 'demo'], array_keys($config));
        $this->assertStringContainsString("// Installed modules switched off on the Modules screen.\n    'disabled_modules' => [", (string) file_get_contents($this->path));

        $this->assertTrue(ConfigWriter::enableModule('ACME/ONE', $this->path));
        $this->assertSame([], $this->read()['disabled_modules']);
        $this->assertSame(['acme/one'], $this->read()['modules']);
    }

    public function test_taking_out_what_has_no_list_changes_nothing(): void
    {
        $this->config("    'resources' => [\n    ],\n");
        $before = (string) file_get_contents($this->path);

        $this->assertTrue(ConfigWriter::removeModule('acme/one', $this->path));
        $this->assertTrue(ConfigWriter::enableModule('acme/one', $this->path));
        $this->assertSame($before, file_get_contents($this->path));
    }

    public function test_taking_out_a_module_leaves_the_others(): void
    {
        $this->config("    'modules' => [\n        'acme/one',\n        'acme/one-more',\n        'acme/two',\n    ],\n");

        $this->assertTrue(ConfigWriter::removeModule('Acme/One', $this->path));
        $this->assertTrue(ConfigWriter::removeModule('acme/absent', $this->path));

        $this->assertSame(['acme/one-more', 'acme/two'], $this->read()['modules']);
    }

    public function test_a_missing_config_is_reported_for_every_change(): void
    {
        $missing = $this->path.'.missing';

        $this->assertFalse(ConfigWriter::addResource('App\\Models\\Article', $missing));
        $this->assertFalse(ConfigWriter::addModule('acme/one', $missing));
        $this->assertFalse(ConfigWriter::removeModule('acme/one', $missing));
        $this->assertFalse(ConfigWriter::disableModule('acme/one', $missing));
        $this->assertFalse(ConfigWriter::enableModule('acme/one', $missing));
        $this->assertFileDoesNotExist($missing);
    }
}
