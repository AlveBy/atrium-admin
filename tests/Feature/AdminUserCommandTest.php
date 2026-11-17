<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * atrium:user: the first administrator, the next ones, and a way back in.
 */
final class AdminUserCommandTest extends TestCase
{
    public function test_the_questions_are_asked_for_what_the_options_leave_out(): void
    {
        $this->artisan('atrium:user')
            ->expectsQuestion('Enter admin name', 'Ann')
            ->expectsQuestion('Enter admin email', 'ann@example.com')
            ->expectsQuestion('Enter admin password', 'long-enough')
            ->expectsOutput("Superadmin 'Ann' created successfully!")
            ->expectsTable(['ID', 'Name', 'Email'], [[1, 'Ann', 'ann@example.com']])
            ->assertSuccessful();

        $user = AdminUser::query()->sole();
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->isSuperadmin());
        $this->assertTrue(Hash::check('long-enough', $user->password));
        $this->assertNotSame('long-enough', $user->password);
    }

    public function test_the_next_administrator_is_told_to_get_a_role(): void
    {
        $this->admin();

        $this->artisan('atrium:user', ['--name' => 'Bob', '--email' => 'bob@example.com', '--password' => '12345678'])
            ->expectsOutput("Admin user 'Bob' created successfully! Give them a role in the panel: Roles, Administrators.")
            ->assertSuccessful();

        $this->assertFalse(AdminUser::query()->where('email', 'bob@example.com')->sole()->isSuperadmin());
    }

    /**
     * @return array<string,array{0:array<string,string>,1:string}>
     */
    public static function refusals(): array
    {
        return [
            'no name' => [['--name' => '', '--email' => 'new@example.com', '--password' => 'long-enough'], 'All fields are required.'],
            'no e-mail' => [['--name' => 'New', '--email' => '', '--password' => 'long-enough'], 'All fields are required.'],
            'no password' => [['--name' => 'New', '--email' => 'new@example.com', '--password' => ''], 'All fields are required.'],
            'taken e-mail' => [['--name' => 'New', '--email' => 'admin@example.com', '--password' => 'long-enough'], "User with email 'admin@example.com' already exists."],
            'not an e-mail' => [['--name' => 'New', '--email' => 'new.example.com', '--password' => 'long-enough'], 'Invalid email format.'],
            'seven characters' => [['--name' => 'New', '--email' => 'new@example.com', '--password' => '1234567'], 'Password must be at least 8 characters.'],
        ];
    }

    /**
     * @param  array<string,string>  $options
     */
    #[DataProvider('refusals')]
    public function test_a_bad_answer_creates_nobody(array $options, string $error): void
    {
        $this->admin();

        $this->artisan('atrium:user', $options)->expectsOutput($error)->assertFailed();

        $this->assertSame(1, AdminUser::query()->count());
    }

    public function test_superadmin_promotes_the_owner_of_an_existing_e_mail_and_changes_nothing_else(): void
    {
        $this->admin();
        $bob = AdminUser::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'old-password', 'is_active' => true]);

        $this->artisan('atrium:user', ['--email' => 'bob@example.com', '--superadmin' => true, '--password' => 'ignored-password'])
            ->expectsOutput("'Bob' is a superadmin now.")
            ->assertSuccessful();

        $bob->refresh();
        $this->assertTrue($bob->isSuperadmin());
        $this->assertTrue(Hash::check('old-password', $bob->password));
        $this->assertSame(2, AdminUser::query()->count());
    }

    public function test_superadmin_with_a_new_e_mail_creates_a_superadmin(): void
    {
        $this->admin();

        $this->artisan('atrium:user', ['--name' => 'Cid', '--email' => 'cid@example.com', '--superadmin' => true])
            ->expectsQuestion('Enter admin password', 'long-enough')
            ->expectsOutput("Superadmin 'Cid' created successfully!")
            ->assertSuccessful();

        $this->assertTrue(AdminUser::query()->where('email', 'cid@example.com')->sole()->isSuperadmin());
    }

    public function test_superadmin_without_an_e_mail_asks_for_everything(): void
    {
        $this->artisan('atrium:user', ['--superadmin' => true])
            ->expectsQuestion('Enter admin name', 'Dee')
            ->expectsQuestion('Enter admin email', 'dee@example.com')
            ->expectsQuestion('Enter admin password', 'long-enough')
            ->assertSuccessful();

        $this->assertTrue(AdminUser::query()->sole()->isSuperadmin());
    }
}
