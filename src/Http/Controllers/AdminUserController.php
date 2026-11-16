<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Models\AdminUser;

class AdminUserController extends AdminController
{
    protected string $model = AdminUser::class;

    /**
     * Get Inertia page path prefix.
     */
    protected function getPagePrefix(): string
    {
        return 'Admin/Users';
    }
}
