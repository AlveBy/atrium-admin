<?php

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

    public function test_atrium_user_refuses_a_short_password(): void
    {
        $this->artisan('atrium:user', ['--name' => 'Ann', '--email' => 'ann@example.com', '--password' => 'short'])
            ->assertFailed();
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
