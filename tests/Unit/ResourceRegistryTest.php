<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Tests\Fixtures\NotAdministrable;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use InvalidArgumentException;

final class ResourceRegistryTest extends TestCase
{
    public function test_a_configured_model_becomes_a_module_keyed_by_its_route_prefix(): void
    {
        $registry = new ResourceRegistry;

        $this->assertSame(['sites' => Site::class, 'languages' => Language::class, 'posts' => Post::class], $registry->all());
        $this->assertSame('posts', $registry->keyOf(Post::class));
        $this->assertSame(Post::class, $registry->modelForRoute('admin.posts.edit'));
    }

    public function test_unknown_routes_resolve_to_nothing(): void
    {
        $registry = new ResourceRegistry;

        $this->assertNull($registry->modelForRoute('admin.users.index'));
        $this->assertNull($registry->modelForRoute('home'));
        $this->assertNull($registry->modelForRoute(null));
    }

    public function test_a_model_without_the_contract_is_rejected_with_a_clear_message(): void
    {
        config(['atrium.resources' => [NotAdministrable::class]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an Eloquent model that implements');

        (new ResourceRegistry)->all();
    }
}
