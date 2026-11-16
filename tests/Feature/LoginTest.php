<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\TestCase;

final class LoginTest extends TestCase
{
    public function test_an_admin_signs_in_with_the_right_password(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->admin();

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'nope'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_a_deactivated_admin_is_refused(): void
    {
        $this->admin()->update(['is_active' => false]);

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }
}
