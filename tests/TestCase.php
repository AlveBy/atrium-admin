<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests;

use Atrium\Admin\AtriumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AtriumServiceProvider::class];
    }
}
