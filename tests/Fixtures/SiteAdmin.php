<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Models\AdminUser;

/** A project's own administrator model, configured as atrium.auth.model. */
final class SiteAdmin extends AdminUser
{
    protected $table = 'admin_users';
}
