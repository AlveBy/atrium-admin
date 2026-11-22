<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Concerns;

use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;

/**
 * An administrator who is no superadmin and holds exactly the permissions given.
 */
trait CreatesEditors
{
    /**
     * @param  array<int,string>  $permissions
     */
    protected function editor(array $permissions, string $email = 'editor@example.com'): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => $email, 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role of '.$email, 'permissions' => $permissions]));

        return $user->fresh();
    }
}
