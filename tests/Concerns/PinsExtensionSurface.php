<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Concerns;

use Atrium\Admin\Tests\Support\ExtensionSurface;
use ReflectionMethod;

/**
 * Mutation testing runs only the test classes that execute a method, so the
 * check of its visibility has to live in one of them: the classes using this
 * trait are the ones that run the controllers, traits and models of the list.
 */
trait PinsExtensionSurface
{
    public function test_every_method_of_the_extension_surface_is_as_visible_as_it_says(): void
    {
        $wrong = [];

        foreach (ExtensionSurface::methods() as $name => [$class, $method, $visibility]) {
            $reflection = new ReflectionMethod($class, $method);
            $is = $reflection->isPublic() ? 'public' : ($reflection->isProtected() ? 'protected' : 'private');

            if ($is !== $visibility) {
                $wrong[] = "{$name} is {$is}, not {$visibility}";
            }
        }

        $this->assertSame([], $wrong);
    }
}
