<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Tests\Support\ExtensionSurface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Who may call what, one method at a time so that a failure names it. The
 * check also lives in the test classes that run these methods
 * (PinsExtensionSurface): mutation testing runs only those.
 */
final class ExtensionSurfaceTest extends TestCase
{
    /**
     * @return array<string,array{class-string,string,string}>
     */
    public static function methods(): array
    {
        return ExtensionSurface::methods();
    }

    #[DataProvider('methods')]
    public function test_a_method_is_as_visible_as_the_extension_api_says(string $class, string $method, string $visibility): void
    {
        $reflection = new ReflectionMethod($class, $method);

        $this->assertSame($visibility === 'public', $reflection->isPublic());
        $this->assertSame($visibility === 'protected', $reflection->isProtected());
    }
}
