<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Transfer;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\Fixtures\Post;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * What a model says about its import and export is asked from outside the
 * model. An Eloquent model forwards a call to a protected static method to
 * itself, so the call would work all the same: the methods themselves have to
 * be public.
 */
final class ModelHooksVisibilityTest extends TestCase
{
    /**
     * @return array<string,array{class-string,string}>
     */
    public static function methods(): array
    {
        return [
            'the keys of a module' => [Post::class, 'getImportKeys'],
            'the import of a module' => [Post::class, 'isImportableInAdmin'],
            'the export of a module' => [Post::class, 'isExportableInAdmin'],
            'the content flag of a module' => [Post::class, 'isContentModule'],
            'the content flag of the languages' => [Language::class, 'isContentModule'],
            'the content flag of the administrators' => [AdminUser::class, 'isContentModule'],
            'the import of the sites' => [Site::class, 'isImportableInAdmin'],
            'the import of the languages' => [Language::class, 'isImportableInAdmin'],
        ];
    }

    /**
     * @param  class-string  $model
     */
    #[DataProvider('methods')]
    public function test_it_is_a_public_static_method(string $model, string $name): void
    {
        $method = new ReflectionMethod($model, $name);

        // Run it too: the mutation tool sends a test to a line only if the test runs the line.
        $this->assertNotNull($method->invoke(null));
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    public function test_the_administrators_and_the_languages_are_no_content_but_the_sites_and_a_module_are(): void
    {
        $this->assertFalse(AdminUser::isContentModule());
        $this->assertFalse(Language::isContentModule());
        $this->assertTrue(Site::isContentModule());
        $this->assertTrue(Post::isContentModule());
    }
}
