<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Tests\Fixtures\Bare;
use Atrium\Admin\Tests\Fixtures\Journal;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
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

    public function test_a_module_of_a_package_that_is_not_installed_is_left_out_without_stopping_the_list(): void
    {
        $this->app->make(Modules::class)->add(Label::module());
        config(['atrium.resources' => [Label::class, Post::class]]);

        $this->assertSame(['sites' => Site::class, 'languages' => Language::class, 'posts' => Post::class], (new ResourceRegistry)->all());
    }

    public function test_the_content_modules_are_the_ones_that_do_not_say_otherwise(): void
    {
        config(['atrium.resources' => [Post::class, Journal::class, Bare::class]]);
        $registry = new ResourceRegistry;

        $this->assertSame(['sites', 'languages', 'posts', 'journals', 'bares'], array_keys($registry->all()));
        $this->assertSame(
            ['sites' => Site::class, 'posts' => Post::class, 'bares' => Bare::class],
            $registry->content(),
            'a service module and the languages are out; a model that never heard of the flag is content',
        );
    }

    public function test_the_list_is_built_once_until_it_is_flushed(): void
    {
        $registry = new ResourceRegistry;
        $this->assertArrayHasKey('posts', $registry->all());

        config(['atrium.resources' => []]);
        $this->assertArrayHasKey('posts', $registry->all(), 'Kept from the first time.');

        $registry->flush();
        $this->assertArrayNotHasKey('posts', $registry->all());
    }

    public function test_only_a_route_of_the_panel_with_a_module_and_an_action_names_a_module(): void
    {
        $registry = new ResourceRegistry;

        $this->assertNull($registry->modelForRoute('other.admin.posts.edit'));
        $this->assertNull($registry->modelForRoute('admin.posts.edit.copy'));
        $this->assertNull($registry->modelForRoute('admin.posts.Edit'));
        $this->assertSame(Post::class, $registry->modelForRoute('admin.posts.index'));
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
        $this->expectExceptionMessage('Atrium resource ['.NotAdministrable::class.'] must be an Eloquent model that implements '.Administrable::class.'.');

        (new ResourceRegistry)->all();
    }

    public function test_a_name_that_is_not_a_class_is_rejected(): void
    {
        config(['atrium.resources' => ['No\\Such\\Model']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Atrium resource [No\\Such\\Model] is not a class.');

        (new ResourceRegistry)->all();
    }

    public function test_a_resource_that_is_not_a_name_is_rejected(): void
    {
        config(['atrium.resources' => [Post::class, 42]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Atrium resource [42] is not a class.');

        (new ResourceRegistry)->all();
    }
}
