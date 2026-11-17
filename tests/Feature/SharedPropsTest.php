<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\TestCase;

/**
 * The panel's shared props keep what Inertia shares by itself: the
 * validation errors that put a message under the field.
 */
final class SharedPropsTest extends TestCase
{
    public function test_a_failed_sign_in_brings_its_error_to_the_page(): void
    {
        $this->admin();

        $response = $this->from('/admin/login')
            ->followingRedirects()
            ->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong-horse']);

        $response->assertOk();
        $this->assertArrayHasKey('email', (array) $response->inertiaProps('errors'));
    }

    public function test_a_page_without_errors_has_none(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $this->assertSame([], (array) $response->inertiaProps('errors'));
    }
}
