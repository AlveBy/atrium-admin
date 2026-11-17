<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * A row of the administrators' table read by a model that is not AdminUser:
 * what a project that points atrium.auth.model elsewhere would get.
 */
final class PanelAccount extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'admin_users';
}
